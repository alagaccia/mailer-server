<?php

namespace App\Contracts;

use App\Models\Email;

interface BridgeMailer
{
    /**
     * Invia l'email della coda. Ritorna true oppure il messaggio d'errore.
     */
    public function send(Email $email): true|string;

    /**
     * Verifica la connessione SMTP con la configurazione indicata.
     *
     * @param  array<string, mixed>  $config
     */
    public function testConnection(array $config): true|string;

    /**
     * Invia un'email di prova con la configurazione indicata.
     *
     * @param  array<string, mixed>  $config
     */
    public function sendTest(array $config, string $to): true|string;
}
