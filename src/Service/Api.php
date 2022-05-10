<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Api
{
    private $_container;
    private $_client;
    private $_proxy = false;

    public function __construct(ContainerInterface $container, HttpClientInterface $client)
    {
        $this->_container = $container;
        $this->_client = $client;
        if ($this->_container->getParameter('proxy_enable')) {
            $this->_proxy = $this->_container->getParameter('proxy_url');
        }
    }

    private function request($method, $url, $query, $body = '', $header = array())
    {
        if ($this->_proxy) {
            $response = $this->_client->request(
                $method,
                $url,
                ['query' => $query, 'proxy' => $this->_proxy, 'body' => $body, 'headers' => $header]
            );
        } else {
            $response = $this->_client->request(
                $method,
                $url,
                ['query' => $query, 'body' => $body, 'headers' => $header]
            );
        }

        return $response;
    }

    private function getToken()
    {
        return $this->_container->getParameter('token');
    }

    public function getDataboxesCollections($databoxe)
    {
        $response = $this->request('GET',
            $this->_container->getParameter('base_url') . 'api/v1/databoxes/' . $databoxe . '/collections/',
            [
                'oauth_token' => $this->getToken()
            ]);

        return json_decode($response->getContent(false), JSON_INVALID_UTF8_IGNORE);
    }

    public function search($query, $page, $perPage)
    {
        $formData = new FormDataPart(['bases' => '2']);

        $response = $this->request('POST',
            $this->_container->getParameter('base_url') . 'api/v1/search/',
            [
                'oauth_token' => $this->getToken(),
                'query' => $query,
                'per_page' => $perPage,
                'offset_start' => ($page - 1) * $perPage,
                'search_type' => 0
            ], $formData->bodyToString(),
            ['Content-Type' => 'multipart/form-data', 'cache-control' => 'no-cache']);

        //dd(json_decode($response->getContent(false), JSON_INVALID_UTF8_IGNORE));

        return json_decode($response->getContent(false), JSON_INVALID_UTF8_IGNORE);

    }

    public function getCaption($recordId, $databoxId)
    {
        $response = $this->request('GET',
            $this->_container->getParameter('base_url') . "api/v1/records/$databoxId/$recordId/caption/",
            [
                'oauth_token' => $this->getToken()
            ]);

        return json_decode($response->getContent(false), JSON_INVALID_UTF8_IGNORE);
    }

    public function getRecords($query, $page, $perPage)
    {
        $searchResults = $this->search($query, $page, $perPage);

        $objects = array();

        foreach ($searchResults['response']['results']['records'] as $result) {

            $objects['records'][$result['record_id']]['search_result'] = $result;
            $objects['records'][$result['record_id']]['metadata'] = $this->getRecordData($result['record_id'], $result['databox_id']);
        }

        $objects['search']['total'] = $searchResults['response']['total_results'];
        $objects['facets'] = $searchResults['response']['facets'];

        return $objects;
    }

    public function getRecord($recordId, $databoxId)
    {

        $response = $this->request('GET',
            $this->_container->getParameter('base_url') . "api/v1/records/$databoxId/$recordId/",
            [
                'oauth_token' => $this->getToken()
            ]);

        return json_decode($response->getContent(false), JSON_INVALID_UTF8_IGNORE);
    }

    public function getRecordData($record_id, $databox_id)
    {
        $caption = $this->getCaption($record_id, $databox_id);

        $metadata = array();

        foreach ($caption['response']['caption_metadatas'] as $meta) {
            $metadata[$meta['name']] = $meta['value'];
        }

        $metadatas = $this->getMetadata($record_id, $databox_id);

        foreach ($metadatas['response']['record_metadatas'] as $meta) {
            $metadata[$meta['name']] = $meta['value'];
        }

        $thumb = $this->getRecord($record_id, $databox_id);

        if (isset($thumb['response']['record']['thumbnail'])) {
            $metadata['thumbnail'] = $thumb['response']['record']['thumbnail'];
            $metadata['mime_type'] = $thumb['response']['record']['mime_type'];
        }

        return $metadata;
    }

    public function getMetadata($recordId, $databoxId)
    {
        $response = $this->request('GET',
            $this->_container->getParameter('base_url') . "api/v1/records/$databoxId/$recordId/metadatas/",
            [
                'oauth_token' => $this->getToken()
            ]);

        return json_decode($response->getContent(false), JSON_INVALID_UTF8_IGNORE);
    }

    public function isJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}