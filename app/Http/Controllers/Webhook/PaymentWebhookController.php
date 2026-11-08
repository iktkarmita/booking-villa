<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use App\Support\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, string $tenant): JsonResponse
    {
        $model = Tenant::query()->where('slug', $tenant)->first();

        if ($model === null) {
            return response()->json(['result' => 'unknown_tenant'], 404);
        }

        $outcome = app(CurrentTenant::class)->runAs(
            $model,
            fn () => PaymentService::handleWebhook($model, $gateway, $request)
        );

        return response()->json(['result' => $outcome['result']], $outcome['status']);
    }
}
