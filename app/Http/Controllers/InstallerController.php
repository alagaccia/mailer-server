<?php

namespace App\Http\Controllers;

use App\Contracts\BridgeMailer;
use App\Models\ApiKey;
use App\Models\Setting;
use App\Models\User;
use App\Support\EnvWriter;
use App\Support\InstallState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class InstallerController extends Controller
{
    /**
     * Mostra il wizard di installazione.
     */
    public function show(): Response
    {
        return Inertia::render('installer/Install', [
            'envWritable' => is_writable(app()->environmentFilePath()),
        ]);
    }

    /**
     * Step 1: valida i dati del primo utente amministratore.
     */
    public function validateAdmin(Request $request): JsonResponse
    {
        $request->validate($this->adminRules());

        return response()->json(['ok' => true]);
    }

    /**
     * Step 2: valida i dati del database e prova la connessione.
     */
    public function testDatabase(Request $request): JsonResponse
    {
        $db = $request->validate($this->databaseRules());

        $this->assertDatabaseConnects($db, 'host');

        return response()->json(['ok' => true]);
    }

    /**
     * Step 3 (facoltativo): prova la connessione SMTP.
     */
    public function testSmtp(Request $request, BridgeMailer $mailer): JsonResponse
    {
        $smtp = $request->validate($this->smtpRules());

        $result = $mailer->testConnection($smtp);

        if ($result !== true) {
            throw ValidationException::withMessages([
                'host' => __('Connessione SMTP fallita: :error', ['error' => $result]),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Esegue l'installazione: .env, migrazioni, utente admin, impostazioni,
     * API key. Il flag di installazione viene scritto per ultimo, così un
     * fallimento intermedio lascia il wizard ripetibile.
     */
    public function finalize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            ...$this->prefixed('admin', $this->adminRules()),
            ...$this->prefixed('db', $this->databaseRules()),
            ...$this->prefixed('smtp', $this->smtpRules()),
        ]);

        $admin = $validated['admin'];
        $db = $validated['db'];
        $db['prefix'] ??= '';
        $smtp = $validated['smtp'];

        $this->assertDatabaseConnects($db, 'db.host');

        // Configurazione applicata a runtime per questa richiesta; il .env
        // viene scritto solo alla fine, quando migrazioni e dati sono a posto.
        config([
            'database.connections.mysql.host' => $db['host'],
            'database.connections.mysql.port' => (int) $db['port'],
            'database.connections.mysql.database' => $db['database'],
            'database.connections.mysql.username' => $db['username'],
            'database.connections.mysql.password' => $db['password'] ?? '',
            'database.connections.mysql.prefix' => $db['prefix'],
            'database.default' => 'mysql',
        ]);
        DB::purge('mysql');

        // `migrate:fresh` è tra i comandi distruttivi bloccati in produzione da
        // DB::prohibitDestructiveCommands() (AppServiceProvider). Il wizard è
        // un punto d'ingresso volontario e già protetto (richiede le credenziali
        // del DB), quindi qui è legittimo azzerarlo per ricreare l'app da zero:
        // il flag è statico e per-request, torna attivo alla richiesta successiva.
        DB::prohibitDestructiveCommands(false);

        try {
            if ($db['prefix'] === '') {
                $exitCode = Artisan::call('migrate:fresh', ['--force' => true]);
            } else {
                // `migrate:fresh` cancellerebbe tutte le tabelle del database,
                // anche quelle di altre applicazioni: con un prefisso il DB è
                // probabilmente condiviso, quindi azzeriamo solo le nostre.
                $this->dropPrefixedTables($db['prefix']);
                $exitCode = Artisan::call('migrate', ['--force' => true]);
            }
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'db.host' => __('Migrazione del database fallita: :error', ['error' => $e->getMessage()]),
            ]);
        }

        if ($exitCode !== 0) {
            // Artisan::call() intercetta le eccezioni dei comandi e restituisce
            // solo l'exit code: senza questo controllo un fallimento silenzioso
            // della migrazione lascerebbe il DB senza tabelle e si proseguirebbe
            // comunque a creare l'utente admin, causando un errore fuorviante.
            throw ValidationException::withMessages([
                'db.host' => __('Migrazione del database fallita: :error', ['error' => trim(Artisan::output())]),
            ]);
        }

        Setting::flushResolved();

        User::updateOrCreate(
            ['email' => Str::lower($admin['email'])],
            [
                'name' => $admin['name'],
                'password' => $admin['password'],
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        Setting::set('smtp_host', $smtp['host']);
        Setting::set('smtp_port', (string) $smtp['port']);
        Setting::set('smtp_username', $smtp['username'] ?? null);
        Setting::set('smtp_password', $smtp['password'] ?? null);
        Setting::set('smtp_encryption', $smtp['encryption']);
        Setting::set('smtp_from_name', $smtp['from_name'] ?? null);
        Setting::set('smtp_from_address', $smtp['from_address'] ?? null);
        Setting::set('smtp_reply_to', $smtp['reply_to'] ?? null);
        Setting::set('mailer_enabled', '1');

        // Prima chiave API: si chiama "default", le altre si creano dal pannello.
        $apiKey = ApiKey::generateSecret();
        ApiKey::updateOrCreate(['name' => 'default'], ['key' => $apiKey]);

        // Persiste le credenziali per le richieste successive.
        EnvWriter::write([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'] ?? '',
            'DB_PREFIX' => $db['prefix'],
        ]);

        // Un'eventuale configurazione cachata ignorerebbe il nuovo .env.
        Artisan::call('config:clear');

        InstallState::markInstalled(['version' => 1]);

        // Da qui in poi le rotte del wizard rispondono 404: la schermata
        // finale vive su una rotta dedicata, così resta raggiungibile anche
        // dopo un refresh (o un reload forzato dal dev server).
        $token = InstallState::rememberCompletion($apiKey);

        return response()->json([
            'api_key' => $apiKey,
            'complete_token' => $token,
            'complete_url' => route('install.complete', ['token' => $token], false),
        ]);
    }

    /**
     * Schermata finale: mostra la chiave API finché l'utente non la conferma
     * (o finché il token non scade). Non redirige da nessuna parte.
     */
    public function complete(Request $request): Response|RedirectResponse
    {
        $completion = InstallState::completion($request->query('token'));

        if ($completion === null) {
            return redirect('/login');
        }

        return Inertia::render('installer/Complete', [
            'apiKey' => $completion['api_key'],
        ]);
    }

    /**
     * L'utente ha salvato la chiave: il file temporaneo può sparire.
     */
    public function dismissComplete(Request $request): JsonResponse
    {
        if (InstallState::completion($request->input('token')) !== null) {
            InstallState::forgetCompletion();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function adminRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::default()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function databaseRules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            // Solo caratteri sicuri in un identificatore MySQL non quotato;
            // il limite lascia spazio ai nomi di tabelle e indici (max 64).
            'prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_]+$/'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function smtpRules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'string', 'email', 'max:255'],
            'reply_to' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Elimina le tabelle della connessione corrente il cui nome inizia con il
     * prefisso, lasciando intatte quelle di altre applicazioni.
     */
    protected function dropPrefixedTables(string $prefix): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $tables = array_values(array_filter(
            $schema->getTableListing($schema->getCurrentSchemaListing(), schemaQualified: false),
            fn (string $table) => str_starts_with($table, $prefix),
        ));

        if ($tables === []) {
            return;
        }

        // I nomi restituiti includono già il prefisso: Schema::drop() lo
        // aggiungerebbe una seconda volta, quindi si usa l'SQL della grammar.
        $schema->withoutForeignKeyConstraints(fn () => DB::statement(
            DB::connection()->getSchemaGrammar()->compileDropAllTables($tables),
        ));
    }

    /**
     * Prefissa le regole di uno step per la validazione del payload finale.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function prefixed(string $prefix, array $rules): array
    {
        $prefixed = [$prefix => ['required', 'array']];

        foreach ($rules as $key => $rule) {
            $prefixed[$prefix.'.'.$key] = $rule;
        }

        return $prefixed;
    }

    /**
     * Prova la connessione al database con una connessione usa e getta.
     *
     * @param  array<string, mixed>  $db
     */
    protected function assertDatabaseConnects(array $db, string $errorKey): void
    {
        config(['database.connections.__install' => [
            'driver' => 'mysql',
            'host' => $db['host'],
            'port' => (int) $db['port'],
            'database' => $db['database'],
            'username' => $db['username'],
            'password' => $db['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]]);

        DB::purge('__install');

        try {
            DB::connection('__install')->getPdo();
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                $errorKey => __('Connessione al database non riuscita: :error', ['error' => $e->getMessage()]),
            ]);
        } finally {
            DB::purge('__install');
        }
    }
}
