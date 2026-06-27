<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysis;
use App\Services\AdvisorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AdvisorController extends Controller
{
    public function index(Request $request): Response
    {
        $last = AiAnalysis::where('user_id', $request->user()->id)
            ->latest('created_at')
            ->first();

        return Inertia::render('Advisor/Index', [
            'lastAnalysis' => $last ? [
                'createdAt' => $last->created_at->toISOString(),
                'model' => $last->model,
                'payload' => $last->payload,
                'inputTokens' => $last->input_tokens,
                'outputTokens' => $last->output_tokens,
            ] : null,
        ]);
    }

    public function analyze(Request $request, AdvisorService $service): RedirectResponse
    {
        try {
            $service->analyze($request->user());
        } catch (Throwable $e) {
            return back()->with('error', 'No se pudo lanzar el análisis: '.$e->getMessage());
        }

        return back()->with('success', '¡Análisis listo!');
    }
}
