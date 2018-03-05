<?php

namespace TraderInteractive\Api;

use DominionEnterprises\Util;
use DominionEnterprises\Util\Arrays;

/**
 * Class to store API results
 */
final class MongoCache implements Cache
{
    /**
     * Mongo collection for storing cache
     *
     * @var \MongoDB\Collection
     */
    private $collection;

    /**
     * Construct a new instance of MongoCache
     *
     * @param string $url mongo url
     * @param string $db name of mongo database
     * @param string $collection name of mongo collection
     */
    public function __construct($url, $db, $collection)
    {
        Util::ensure(
            true,
            class_exists('\MongoDB\Client'),
            '\RuntimeException',
            ['mongo extension is required for ' . __CLASS__]
        );
        Util::throwIfNotType(['string' => [$url, $db, $collection]], true);
        $mongo = new \MongoDB\Client(
            $url,
            [],
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        );
        $this->collection = $mongo->selectCollection($db, $collection);
    }

    /**
     * @see Cache::set()
     */
    public function set(Request $request, Response $response, $expires = null)
    {
        Util::throwIfNotType(['int' => [$expires]], false, true);

        if ($expires === null) {
            $expiresHeader = null;
            if (!Arrays::tryGet($response->getResponseHeaders(), 'Expires', $expiresHeader)) {
                return;
            }

            $expires = Util::ensureNot(
                false,
                strtotime($expiresHeader[0]),
                "Unable to parse Expires value of '{$expiresHeader[0]}'"
            );
        }

        $id = self::getUniqueId($request);
        $cache = [
            '_id' => $id,
            'httpCode' => $response->getHttpCode(),
            'body' => $response->getResponse(),
            'headers' => $response->getResponseHeaders(),
            'expires' => new \MongoDB\BSON\UTCDateTime(floor($expires * 1000)),
        ];
        $this->collection->replaceOne(['_id' => $id], $cache, ['upsert' => true]);
    }

    /**
     * @see Cache::get()
     */
    public function get(Request $request)
    {
        $cache = $this->collection->findOne(['_id' => self::getUniqueId($request)]);
        if ($cache === null) {
            return null;
        }

        return new Response($cache['httpCode'], $cache['headers'], $cache['body']);
    }

    /**
     * Ensures proper indexes are created on the mongo cache collection
     *
     * @return void
     */
    public function ensureIndexes()
    {
        $this->collection->createIndex(['expires' => 1], ['expireAfterSeconds' => 0, 'background' => true]);
    }

    /**
     * Helper method to get a unique id of an API request.
     *
     * This generator does not use the request headers so there is a chance for conflicts
     *
     * @param Request $request The request from which to generate an unique identifier
     *
     * @return string the unique identifier
     */
    private static function getUniqueId(Request $request)
    {
        return $request->getUrl() . '|' . $request->getBody();
    }
}
