<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendPoSMail extends Mailable
{
    use Queueable, SerializesModels;

    public $encryptedData;
    public $dataArray;
    public $fromName;

    /**
     * Create a new message instance.
     *
     * @param array $encryptedData
     * @param array $dataArray
     * @param string|null $fromName
     * @return void
     */
    public function __construct($encryptedData, $dataArray)
    {
        $this->encryptedData = $encryptedData;
        $this->dataArray = $dataArray;
        $this->fromName = $fromName ?? config('mail.from.name');  // Fallback to .env if not provided
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        return $this->from(config('mail.from.address'), $this->fromName)
                    ->subject($this->dataArray['subject'])
                    ->view('email.pos.send')
                    ->with([
                        'encryptedData' => $this->encryptedData,
                        'dataArray' => $this->dataArray,
                    ]);
    }
}
