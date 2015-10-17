<?php

namespace PHPHub\Handlers\Events;

use PHPHub\Events\Event;
use PHPHub\Events\TopicUpVoted;
use PHPHub\Repositories\Eloquent\NotificationRepository;
use PHPHub\Repositories\NotificationRepositoryInterface;
use PHPHub\Services\PushService\Jpush;

class PushNotificationHandler
{
    /**
     * 订阅的时间和处理的方法.
     *
     * @var array
     */
    protected $subscribe_events = [
        TopicUpVoted::class => 'handle',
    ];

    /**
     * Jpush 对象
     *
     * @var Jpush
     */
    private $jpush = null;
    /**
     * @var NotificationRepositoryInterface
     */
    private $notifications;

    /**
     * PushNotificationHandler constructor.
     *
     * @param NotificationRepositoryInterface $notifications
     */
    public function __construct(NotificationRepository $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * 推送消息.
     *
     * @param $user_ids
     * @param $msg
     * @param $extras
     */
    protected function push($user_ids, $msg, $extras = null)
    {
        if (!$this->jpush) {
            $this->jpush = new Jpush();
        }

        $user_ids = (array) $user_ids;
        $user_ids = array_map(function ($user_id) {
            return 'userid_'.$user_id;
        }, $user_ids);

        $this->jpush
            ->platform('all')
            ->message($msg)
            ->toAlias($user_ids)
            ->extras($extras)
            ->send();
    }

    /**
     * Handle the event.
     *
     * @param Event|TopicUpVoted $event
     */
    public function handle(Event $event)
    {
        $data = [
            'topic_id'     => $event->getTopicId(),
            'body'         => $event->getBody(),
            'from_user_id' => $event->getFromUserId(),
            'user_id'      => $event->getUserId(),
            'type'         => $event->getType(),
            'reply_id'     => $event->getReplyId(),
        ];

        $notification = $this->notifications->create($data);

        $presenter = app('autopresenter')->decorate($notification);

        $this->push($event->getUserId(), $presenter->message(), array_only($data, [
            'topic_id',
            'from_user_id',
            'type',
            'reply_id',
        ]));
    }

    /**
     * 注册监听器给订阅者。.
     *
     * @param \Illuminate\Events\Dispatcher $events
     */
    public function subscribe($events)
    {
        foreach ($this->subscribe_events as $event => $handle_method) {
            $events->listen($event, self::class.'@'.$handle_method);
        }
    }
}