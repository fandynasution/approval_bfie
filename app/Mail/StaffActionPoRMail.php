<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StaffActionPoRMail extends Mailable
{
    use Queueable, SerializesModels;

    public $mailData; // ✅ tambahkan ini

    /**
     * Create a new message instance.
     *
     * @param array $mailData
     * @return void
     */
    public function __construct(string  $mailData)
    {
        $this->mailData = $mailData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->mailData['subject'].' '.$this->mailData['descs'].' No. '.$this->mailData['doc_no'])
                    ->view('email.staffaction.por')
                    ->with(['data' => $this->mailData]);
    }
}