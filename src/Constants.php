<?php


namespace FirebaseWrapper;


class Constants
{
    const CLOUD_PLATFORM_SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    const FIREBASE_URL = 'https://firebase.googleapis.com/v1beta1';
    const FCM_URL = 'https://fcm.googleapis.com/v1';
    const GOOGLE_CLOUD_URL = 'https://cloudresourcemanager.googleapis.com/v1';
    const MULTI_SUBSCRIBE_URL = 'https://iid.googleapis.com/iid/v1:batchAdd';
    const MULTI_UNSUBSCRIBE_URL = 'https://iid.googleapis.com/iid/v1:batchRemove';

    const OPERATION_POLLING_INTERVAL = 1; // seconds
    const OPERATION_POLLING_COUNT = 30;
}