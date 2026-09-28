<?php

declare(strict_types=1);

namespace Rominas\Results\Controllers;

use Rominas\Editions\Model\Edition;
use Rominas\Results\Actions\GetEditionResultsAction;
use Rominas\Results\Resources\EditionResultsResource;
use Rominas\Results\Support\EditionResultsPresenter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Custodian-facing results: view an edition's complete results (live-computed during the review window,
 * or the frozen snapshot once results are published) and export them as CSV. Authorization is the `results`
 * permission via ResultPolicy; the underlying read enforces Scoring's "voting closed" gate.
 */
class ResultsController
{
    public function show(Edition $edition, GetEditionResultsAction $action): EditionResultsResource
    {
        return EditionResultsResource::make($action->execute($edition));
    }

    public function export(Edition $edition, GetEditionResultsAction $action): StreamedResponse
    {
        $data = (new EditionResultsPresenter())->present($action->execute($edition));

        $filename = "results-edition-{$edition->slug}.csv";

        return response()->streamDownload(function () use ($data): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'category',
                'position',
                'nominee_type',
                'nominee_name',
                'academy_points',
                'public_points',
                'academy_share',
                'public_share',
                'final_score',
            ], escape: '');

            foreach ($data['categories'] as $category) {
                foreach ($category['nominees'] as $nominee) {
                    fputcsv($handle, [
                        $category['category']['name'] ?? '',
                        $nominee['position'],
                        $nominee['nominee_type'],
                        $nominee['nominee']['name'] ?? '',
                        $nominee['academy_points'],
                        $nominee['public_points'],
                        $nominee['academy_share'],
                        $nominee['public_share'],
                        $nominee['final_score'],
                    ], escape: '');
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
