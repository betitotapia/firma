<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractSignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public bool $withoutAttachment = false;

    public function __construct(
        public string $subjectLine,
        public string $title,
        public string $stageLabel,
        public string $filePath,
        public string $fileName,
        public ?string $evidenceUrl = null
    ) {}

    public function build()
    {
        $mail = $this->subject($this->subjectLine)
            ->view('emails.contract_signed')
            ->with([
                'title' => $this->title,
                'stageLabel' => $this->stageLabel,
                'evidenceUrl' => $this->evidenceUrl,
                'withoutAttachment' => $this->withoutAttachment,
            ]);

        if (!$this->withoutAttachment && file_exists($this->filePath)) {
            $mail->attach($this->filePath, [
                'as' => $this->fileName,
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}