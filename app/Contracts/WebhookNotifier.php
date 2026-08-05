<?php

namespace App\Contracts;

use App\Models\Email;

interface WebhookNotifier
{
    /**
     * Notifica l'esito dell'elaborazione di un'email al webhook di
     * destinazione (quello della riga, altrimenti quello di default).
     *
     * Ritorna true se il webhook ha risposto 2xx, il messaggio d'errore se la
     * chiamata è fallita, null se non c'è nessun webhook da chiamare.
     */
    public function notify(Email $email): true|string|null;

    /**
     * Invia una notifica di prova all'URL indicato, autenticandola con il
     * token e la firma HMAC passati (o, se assenti, con quelli salvati nelle
     * impostazioni).
     */
    public function test(string $url, ?string $token = null, ?string $secret = null, ?string $header = null): true|string;
}
