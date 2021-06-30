<?php

namespace FirebaseWrapper;

use stdClass;

class PushMessage
{
    private $name;
    private $data;
    private $notification;
    private $android;
    private $webpush;
    private $apns;
    private $fcmOptions;
    private $token;
    private $topic;
    private $condition;

    /**
     * PushMessage constructor.
     * @param $pushData
     */
    public function __construct($pushData)
    {
        if (isset($pushData->name))
            $this->name = $pushData->name;

        if (isset($pushData->topic))
            $this->topic = $pushData->topic;
        elseif (isset($pushData->token))
            $this->token = $pushData->token;
        elseif (isset($pushData->condition))
            $this->condition = $pushData->condition;

        if (isset($pushData->data) && is_object($pushData->data))
            $this->data = $pushData->data;

        $this->notification = new stdClass();
        $this->notification->title = $pushData->title;
        $this->notification->body = $pushData->body;

        if (isset($pushData->image))
            $this->notification->image = $pushData->image;

        $this->webpush = new stdClass();
        $this->webpush->headers = new stdClass();
        $this->webpush->headers->ttl = strval(intval($pushData->ttl) * 86400);
        $this->webpush->notification = new stdClass();
        $this->webpush->notification->title = $pushData->title;
        $this->webpush->notification->body = $pushData->body;
        $this->webpush->notification->icon = 'https://cdn0.routee.net/resources/img/notification_icon.png';
        $this->webpush->notification->requireInteraction = true;

        if (isset($pushData->launchUrl)) {
            $this->webpush->fcmOptions = new stdClass();
            $this->webpush->fcmOptions->link = $pushData->launchUrl;
        }

        // TODO: android, apns, web push full
    }

    /**
     * @return string
     */
    public function toJson()
    {
        $json = json_encode($this->data());
        return ($json === false) ? '{}' : $json;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * @return object
     */
    public function data()
    {
        $object = new stdClass();

        if (isset($this->name))
            $object->name = $this->name;

        if (isset($this->data))
            $object->data = $this->data;

        if (isset($this->notification))
            $object->notification = $this->notification;

        if (isset($this->android))
            $object->android = $this->android;

        if (isset($this->webpush))
            $object->webpush = $this->webpush;

        if (isset($this->apns))
            $object->apns = $this->apns;

        if (isset($this->fcmOptions))
            $object->fcm_options = $this->fcmOptions;

        if (isset($this->token))
            $object->token = $this->token;

        if (isset($this->topic))
            $object->topic = $this->topic;

        if (isset($this->condition))
            $object->condition = $this->condition;

        return $object;
    }
}