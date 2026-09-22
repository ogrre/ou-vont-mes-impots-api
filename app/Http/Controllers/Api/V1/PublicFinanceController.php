<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Api\HomePagePresenter;
use App\Services\Api\PublicFinanceQuery;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicFinanceController extends Controller
{
    public function years(PublicFinanceQuery $query): JsonResponse
    {
        return response()->json(['years' => $query->years()]);
    }

    public function sources(PublicFinanceQuery $query): JsonResponse
    {
        return response()->json(['sources' => $query->sources()]);
    }

    public function overview(int $year, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json($query->overview($year));
    }

    public function home(int $year, PublicFinanceQuery $query, HomePagePresenter $presenter): JsonResponse
    {
        return $this->cachedJson("api:v1:home:{$year}:v1", fn (): array => $presenter->present($query->overview($year)));
    }

    public function budgetStateMissions(int $year, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json($query->budgetStateMissions($year));
    }

    public function budgetStateMission(int $year, string $mission, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json($query->budgetStateMission($year, $mission));
    }

    public function budgetStateProgramme(int $year, string $programme, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json($query->budgetStateProgramme($year, $programme));
    }

    public function budgetStateProgrammeActions(int $year, string $programme, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json($query->budgetStateProgrammeActions($year, $programme));
    }

    public function budgetStateAction(int $year, string $action, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json($query->budgetStateAction($year, $action));
    }

    public function budgetStateDistribution(int $year, Request $request, PublicFinanceQuery $query): JsonResponse
    {
        $data = $request->validate(['mission' => ['nullable', 'string'], 'programme' => ['nullable', 'string'], 'measurement' => ['nullable', 'in:commitment_authorization,payment_credit'], 'stage' => ['nullable', 'in:initial_budget,executed'], 'unit' => ['nullable', 'in:amount,percent,per_100']]);

        $mission = $data['mission'] ?? null;
        $programme = $data['programme'] ?? null;
        $measurement = $data['measurement'] ?? 'payment_credit';
        $stage = $data['stage'] ?? 'executed';
        $unit = $data['unit'] ?? 'per_100';
        $key = 'api:v1:budget-distribution:'.sha1(implode('|', [$year, $mission, $programme, $measurement, $stage, $unit]));

        return $this->cachedJson($key, fn (): array => $query->budgetStateDistribution($year, $mission, $programme, $measurement, $stage, $unit));
    }

    /** @param Closure(): array<string,mixed> $callback */
    private function cachedJson(string $key, Closure $callback): JsonResponse
    {
        return response()->json(Cache::remember($key, now()->addHours(6), $callback))
            ->header('Cache-Control', 'public, max-age=3600, stale-while-revalidate=86400');
    }

    public function categories(string $classification, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json(['classification' => $classification, 'categories' => $query->categories($classification)]);
    }

    public function children(string $classification, string $category, PublicFinanceQuery $query): JsonResponse
    {
        $items = $query->categories($classification);
        $parent = collect($items)->first(fn ($item) => $item['slug'] === $category || $item['code'] === $category);

        return response()->json(['classification' => $classification, 'category' => $category, 'categories' => $parent ? $query->categories($classification, $parent['id']) : []]);
    }

    public function cofogDetail(int $year, string $category, PublicFinanceQuery $query): JsonResponse
    {
        return response()->json($query->cofogDetail($year, $category));
    }

    public function history(Request $request, PublicFinanceQuery $query): JsonResponse
    {
        $data = $request->validate(['metric' => ['required', 'in:expenditure,revenue,tax,social_contribution,deficit,debt'], 'classification' => ['nullable', 'string'], 'category' => ['nullable', 'string'], 'scope' => ['nullable', 'string'], 'accounting_basis' => ['nullable', 'in:national_accounts,budgetary'], 'from' => ['required', 'integer', 'min:1949', 'max:2200'], 'to' => ['required', 'integer', 'min:1949', 'max:2200', 'gte:from']]);

        return response()->json(['metric' => $data['metric'], 'from' => $data['from'], 'to' => $data['to'], 'accounting_basis' => $data['accounting_basis'] ?? null, 'items' => $query->history($data['metric'], $data['classification'] ?? null, $data['category'] ?? null, $data['scope'] ?? null, $data['from'], $data['to'], $data['accounting_basis'] ?? null)]);
    }

    public function search(Request $request, PublicFinanceQuery $query): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'year' => ['nullable', 'integer', 'min:1949', 'max:2200'],
            'scope' => ['nullable', 'string', 'max:80'],
            'types' => ['nullable', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json($query->search($data['q'], $data['year'] ?? null, $data['scope'] ?? null, $data['types'] ?? null, $data['limit'] ?? 20));
    }
}
