<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssetBlastMail extends Mailable
{
    use Queueable, SerializesModels;

    public $cleanDeptDescs;
    public $staff_name;
    public $filepath;

    public function __construct($cleanDeptDescs, $staff_name, $filepath)
    {
        $this->cleanDeptDescs = $cleanDeptDescs;
        $this->staff_name = $staff_name;
        $this->filepath = $filepath;
    }

    public function build()
    {
        return $this->subject('Fixed Asset Not Yet Audit')
                    ->text('email.blastfa.send') // view ini hanya plain text
                    ->attach($this->filepath, [
                        'as' => 'FA_' . $this->cleanDeptDescs . '.xlsx',
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);   // lampirkan file di sini
    }
}