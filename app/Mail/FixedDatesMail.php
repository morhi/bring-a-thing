<?php

namespace App\Mail;

use App\Models\Event;
use App\Support\WallClock;
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
     * Times are shown in the recipient's zone (section 9).
     *
     * @param  Collection<int, Event>  $events  in calendar order
     */
    public function __construct(protected string $pollTitle, public string $url, public Collection $events, public string $zone = WallClock::DEFAULT_ZONE) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Dates fixed: :title', ['title' => $this->singleLineTitle()]),
        );
    }

    /**
     * Collapse the poll title to one line.
     *
     * Newlines would let a title inject extra markdown blocks or subject headers.
     */
    private function singleLineTitle(): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $this->pollTitle));
    }

    /**
     * Escape markdown control characters of the poll title.
     *
     * The title is user input rendered inside a markdown template, so links and formatting must stay literal.
     */
    private function markdownSafeTitle(): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!<>|~])/', '\\\\$1', $this->singleLineTitle());
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.polls.dates-fixed',
            with: [
                'pollTitle' => $this->markdownSafeTitle(),
                'url' => $this->url,
                'events' => $this->events,
                'zone' => $this->zone,
            ],
        );
    }
}
