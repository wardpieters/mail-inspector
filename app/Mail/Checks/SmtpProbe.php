<?php

namespace App\Mail\Checks;

use App\Mail\Smtp\SmtpSession;
use App\Mail\Smtp\SmtpSessionFactory;
use App\Mail\Value\EndpointTarget;
use App\Mail\Value\Finding;
use App\Mail\Value\ProbeResult;
use App\Mail\Value\ScanBudget;
use App\Mail\Value\TlsObservation;

class SmtpProbe
{
    public function __construct(private SmtpSessionFactory $factory) {}

    public function probe(EndpointTarget $target, ScanBudget $budget): ProbeResult
    {
        $findings = [];
        $session = null;
        $connected = false;
        $capabilities = [];
        $tls = null;
        try {
            $session = $this->factory->open($target, $budget);
            $connected = true;
            $findings[] = Finding::make('connect', 'pass', 'SMTP port 25 connected', 'This literal address was connected independently.', ['ip' => $target->ip, 'family' => $target->family]);
            $greeting = $session->greeting();
            $findings[] = Finding::make('answer', $greeting->code === 220 ? 'pass' : 'fail', $greeting->code === 220 ? 'SMTP greeting received' : 'SMTP service did not accept a session', 'Expected 220 greeting.', ['reply' => $greeting->jsonSerialize()], $greeting->code === 220 ? null : 'Review the SMTP listener and access policy.', $greeting->code === 220 ? 'none' : 'high');
            if ($greeting->code !== 220) {
                return $this->finish($findings, $session, $connected, $capabilities, $tls);
            }
            $helo = config('mail_scan.helo');
            if (! is_string($helo) || ! preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z0-9-]+$/iD', $helo)) {
                throw new \RuntimeException('Scanner HELO hostname is not configured correctly.');
            }
            $ehlo = $session->command('EHLO '.$helo);
            $extended = $ehlo->code === 250;
            if (! $extended) {
                $ehlo = $session->command('HELO '.$helo);
            }
            $findings[] = Finding::make('helo', $ehlo->code === 250 ? 'pass' : 'fail', $extended ? 'Extended SMTP greeting accepted' : 'SMTP HELO fallback', 'EHLO extension support is recorded separately.', ['reply' => $ehlo->jsonSerialize(), 'fallback' => ! $extended], $ehlo->code === 250 ? null : 'Review SMTP greeting handling.', $ehlo->code === 250 ? 'none' : 'high');
            if ($ehlo->code !== 250) {
                return $this->finish($findings, $session, $connected, $capabilities, $tls);
            }
            $capabilities = $extended ? $ehlo->capabilities() : [];
            $sender = (string) config('mail_scan.sender', '');
            if ($sender !== '' && ! filter_var($sender, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Scanner sender identity is invalid.');
            }
            $mail = $session->command('MAIL FROM:<'.$sender.'>');
            $findings[] = Finding::make('from', $mail->accepted() ? 'pass' : 'inconclusive', $mail->accepted() ? 'Envelope sender accepted' : 'Envelope sender rejected', 'A MAIL FROM response does not prove message delivery.', ['reply' => $mail->jsonSerialize(), 'null_sender' => $sender === '']);
            $reset = $session->command('RSET');
            if (! $reset->accepted()) {
                throw new \RuntimeException('RSET failed; envelope probe stopped.');
            }
            $findings[] = $this->relay($session);
            $session->command('QUIT');
        } catch (\Throwable $e) {
            $local = in_array($e->getCode(), [47, 97, 99], true);
            $findings[] = Finding::make($connected ? 'smtp_dialogue' : 'connect', $local ? 'inconclusive' : ($connected ? 'inconclusive' : 'fail'), $local ? 'Scanner cannot use this address family' : 'SMTP probe did not complete', substr($e->getMessage(), 0, 500), ['ip' => $target->ip, 'error_code' => $e->getCode()], $local ? 'Configure working IPv6 egress on the scanner host.' : ($connected ? null : 'Check outbound port 25, routing, firewall, and the remote listener.'), $local || $connected ? 'none' : 'high');
        } finally {
            if ($session) {
                $session->close();
            }
        }

        return $this->finish($findings, $session, $connected, $capabilities, $tls);
    }

    private function relay(SmtpSession $session): Finding
    {
        $sender = (string) config('mail_scan.relay_sender');
        $recipient = (string) config('mail_scan.relay_recipient');
        if (! filter_var($sender, FILTER_VALIDATE_EMAIL) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return Finding::make('open_relay', 'skipped', 'Relay probe not configured', 'Set operator-controlled external sender and recipient identities. No message is transmitted.');
        }
        $mail = $session->command('MAIL FROM:<'.$sender.'>');
        if (! $mail->accepted()) {
            return Finding::make('open_relay', 'inconclusive', 'Relay sender was rejected', 'Recipient relay policy was not reached.', ['mail' => $mail->jsonSerialize()]);
        }
        $recipientReply = $session->command('RCPT TO:<'.$recipient.'>');
        $reset = $session->command('RSET');
        if (! $reset->accepted()) {
            throw new \RuntimeException('RSET failed after relay probe.');
        }
        $accepted = in_array($recipientReply->code, [250, 251], true);
        $explicit = $recipientReply->code >= 500 && preg_match('/relay(?:ing)? (?:denied|not (?:allowed|permitted))|unable to relay|relay access denied/i', implode(' ', $recipientReply->lines));

        return Finding::make('open_relay', $accepted ? 'warning' : ($explicit ? 'pass' : 'inconclusive'), $accepted ? 'Suspected relay acceptance' : ($explicit ? 'External relay explicitly denied' : 'Relay policy could not be established'), 'Only envelope commands were sent; DATA is never sent and acceptance is not proof of delivery.', ['reply' => $recipientReply->jsonSerialize(), 'delivered' => false], $accepted ? 'Review relay restrictions using an authorized delivery test.' : null, $accepted ? 'high' : 'none');
    }

    private function finish(array $findings, ?SmtpSession $session, bool $connected, array $capabilities, ?TlsObservation $tls): ProbeResult
    {
        $keys = array_column(array_map(fn ($f) => $f->jsonSerialize(), $findings), 'key');
        foreach (['answer', 'helo', 'from', 'open_relay'] as $key) {
            if (! in_array($key, $keys, true)) {
                $findings[] = Finding::make($key,'skipped','Check could not run','A prerequisite SMTP step did not complete.');
            }
        }
        if ($session) {
            $session->close();
        }

return new ProbeResult($findings,$connected,$capabilities,$tls,$session ? $session->transcript() : []);
    }
}
