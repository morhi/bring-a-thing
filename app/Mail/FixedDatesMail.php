<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class FixedDatesMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Summarize the dates an organizer fixed from a poll (F-30).
     *
     * @param  Collection<int, Event>  $events  in calendar order
     */
    public function __construct(public string $pollTitle, public string $url, public Collection $events) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Dates fixed: :title', ['title' => $this->pollTitle]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.polls.dates-fixed',
            with: [
                'pollTitle' => $this->pollTitle,
                'url' => $this->url,
                'events' => $this->events,
            ],
        );
    }
}
