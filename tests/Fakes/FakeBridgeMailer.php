<?php

namespace Tests\Fakes;

use App\Contracts\BridgeMailer;
use App\Models\Email;

class FakeBridgeMailer implements BridgeMailer
{
    /** @var list<int> */
    public array $sentIds = [];

    /**
     * Risultato restituito da send(); una stringa simula un errore SMTP.
     */
    public true|string $result = true;

    /**
     * Mappa id email => risultato, per differenziare i destinatari.
     *
     * @var array<int, true|string>
     */
    public array $resultsById = [];

    public function send(Email $email): true|string
    {
        $this->sentIds[] = $email->id;

        return $this->resultsById[$email->id] ?? $this->result;
    }

    public function testConnection(array $config): true|string
    {
        return $this->result;
    }

    public function sendTest(array $config, string $to): true|string
    {
        return $this->result;
    }
}
