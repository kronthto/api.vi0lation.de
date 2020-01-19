<?php

namespace App\Services;

use App\Models\PushSub;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushSendService
{
    /** @var WebPush */
    protected $pushObject;

    /**
     * Get and save/cache a WebPush instance.
     *
     * @return WebPush
     */
    protected function getWebpush(): WebPush
    {
        if (!isset($this->pushObject)) {
            $this->pushObject = new WebPush(array(
                'VAPID' => array(
                    'subject' => 'api.vi0lation.de',
                    'publicKey' => 'BKj3l3KSXQlD52OW8NszYbgGYmIuk_BUevF5u7TDTfaY9HDtPO_iwcYHgY1jpAkBouP5MwdQ2B-45acMXZMDVRg',
                    'privateKey' => config('cr.pushPrivate'),
                ),
            ));
            $this->pushObject->setReuseVAPIDHeaders(true);
        }

        return $this->pushObject;
    }

    /**
     * Sends a Push message.
     *
     * @param PushSub $subscription
     * @param string $payload
     * @param bool|true $flush
     *
     * @return array|bool
     */
    public function sendMessageDirectly(PushSub $subscription, string $payload, bool $flush = true)
    {
        $webpush = $this->getWebpush();

        return $webpush->sendNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'keys' => ['p256dh' => $subscription->key, 'auth' => $subscription->token]
            ]),
            $payload,
            $flush
        );
    }

    /**
     * Queues a Push message.
     *
     * @param PushSub $subscription
     * @param string $payload
     *
     * @return array|bool
     */
    public function queueMessage(PushSub $subscription, string $payload)
    {
        return $this->sendMessageDirectly($subscription, $payload, false);
    }

    /**
     * Clear the buffers.
     *
     * @return array
     */
    public function sendQueued()
    {
        if ($this->pushObject) {
            $this->pushObject->flush();
        }
    }
}