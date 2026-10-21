<?php

namespace App\Http\Controllers;

use App\Services\ContactExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Downloads the Contacts list (with its current search and card filter) as CSV, Excel, or vCards. */
class ContactExportController extends Controller
{
    public function __construct(
        private readonly ContactExportService $exports,
    ) {}

    public function __invoke(Request $request, string $format): Response
    {
        $user = $request->user();
        $search = $request->string('search')->trim()->value() ?: null;
        $card = $request->query('card');
        // "none", or one of the user's own card ids. Anything else is ignored (and never reaches the database).
        $card = $card === 'none' || (is_string($card) && Str::isUuid($card) && $user->cards()->whereKey($card)->exists())
            ? $card
            : null;

        if ($format === 'vcf') {
            return response()->streamDownload(function () use ($user, $search, $card) {
                foreach ($this->exports->vcards($user, $search, $card) as $vcard) {
                    echo $vcard;
                }
            }, $this->exports->filename('vcf'), ['Content-Type' => 'text/vcard; charset=utf-8']);
        }

        return response()
            ->download($this->exports->spreadsheet($user, $format, $search, $card), $this->exports->filename($format))
            ->deleteFileAfterSend();
    }
}
