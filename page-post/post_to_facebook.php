<?php
// file: post_to_facebook.php

require_once __DIR__ . '/vendor/autoload.php';

function postVideoToFacebookPage($appId, $appSecret, $pageId, $pageAccessToken, $message, $videoPath) {
    
    // បង្កើត Object Facebook
    $fb = new \Facebook\Facebook([
        'app_id' => $appId,
        'app_secret' => $appSecret,
        'default_graph_version' => 'v15.0', // ប្រើ Version ចុងក្រោយដែលអ្នកបានសាកល្បង
    ]);

    try {
        // រៀបចំទិន្នន័យវីដេអូសម្រាប់ Upload
        $videoData = [
            'description' => $message,
            'source' => $fb->videoToUpload($videoPath),
        ];

        // ផ្ញើ Request ទៅកាន់ Graph API ដើម្បីបង្ហោះវីដេអូ
        // Endpoint គឺ '/{page-id}/videos'
        $response = $fb->post(
            '/' . $pageId . '/videos',
            $videoData,
            $pageAccessToken
        );
        
        $graphNode = $response->getGraphNode();
        // បើជោគជ័យ វានឹងប្រគល់ ID របស់ Post មកវិញ
        return ['status' => 'success', 'message' => 'វីដេអូ​ត្រូវ​បាន​បង្ហោះ​ដោយ​ជោគជ័យ! Post ID: ' . $graphNode['id']];

    } catch(Facebook\Exception\ResponseException $e) {
        // ករណី API បោះ Error មកវិញ
        return ['status' => 'error', 'message' => 'Graph API Error: ' . $e->getMessage()];
    } catch(Facebook\Exception\SDKException $e) {
        // ករណី SDK មានបញ្ហា (ឧ. បញ្ហា Network)
        return ['status' => 'error', 'message' => 'Facebook SDK Error: ' . $e->getMessage()];
    }
}
?>