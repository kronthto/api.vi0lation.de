<?php

namespace App\Http\Controllers;

use App\Models\PushSub;
use Illuminate\Http\Request;

class PushSubController extends Controller
{
    protected function verifyPayload(array $payload): bool
    {
        if (!array_key_exists('endpoint', $payload)) {
            return false;
        }
        if (!array_key_exists('key', $payload)) {
            return false;
        }
        if (!array_key_exists('token', $payload)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $id
     *
     * @return PushSub|null
     */
    protected function findAndValidateExistingSub($id): ?PushSub
    {
        $existing = PushSub::find($id);
        if (!$existing) {
            return null;
        }

        return $existing;
    }

    // Handle new subs and updates
    public function subscribe(Request $request)
    {
        $payload = $request->json()->all();
        if (!$this->verifyPayload($payload)) {
            return response('Payload is missing parameters', 400);
        }

        $id = PushSub::createIdByEndpoint($payload['endpoint']);
        $existing = $this->findAndValidateExistingSub($id);
        if ($existing) {
            $prevConfig = $existing->config;
            $prevConfig['crevents'] = (array) $payload['events'];
            $existing->config = $prevConfig;
            $existing->update([
                'endpoint' => $payload['endpoint'],
                'key' => $payload['key'],
                'token' => $payload['token'],
                'origin' => $request->header('Origin'),
            ]);

            return $existing;
        }

        $newmodel = new PushSub([
            'id' => $id,
            'endpoint' => $payload['endpoint'],
            'key' => $payload['key'],
            'token' => $payload['token'],
            'origin' => $request->header('Origin'),
            'config' => ['crevents' => (array) $payload['events']]
        ]);
        $newmodel->save();

        return response($newmodel, 201);
    }

    // Handle DELETE calls
    public function unsubscribe(Request $request)
    {
        $payload = $request->json()->all();
        if (!$this->verifyPayload($payload)) {
            return response('Payload is missing parameters', 400);
        }

        $id = PushSub::createIdByEndpoint($payload['endpoint']);

        $existing = $this->findAndValidateExistingSub($id);
        $deleteCount = 0;
        if ($existing) {
            $existing->delete();
            ++$deleteCount;
        }

        return response()->json(['deleted' => $deleteCount]);
    }
}