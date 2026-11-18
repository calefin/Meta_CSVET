<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BudgetPdfController extends Controller
{
    public function __invoke(Request $request, Budget $budget): Response
    {
        abort_unless(
            $request->user()->isAdmin() || $budget->user_id === $request->user()->id,
            403
        );

        $budget->load('items', 'recommendedWork.ritualWork', 'user');

        $pdf = Pdf::loadView('pdf.budget', ['budget' => $budget]);

        return $pdf->stream("orcamento-{$budget->id}.pdf");
    }
}
