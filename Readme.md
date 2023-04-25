# Shopware OAuth2 Client v2 #

Use it to send requests to Shopware 6 API.

Controls the authentication and renewal of the token independently.

## Installation ##

Install package:

```composer require axro-developer/shopware-oauth2-client```

## Usage ##

### Requests ###

```
use AxroShopware\Client\ShopwareClient;

// Create an instance with your credentials
$client = new ShopwareClient(
    $baseUrl, $clientId, $clientSecret
);

// add your monolog logger LoggerInterface
$client->setLogger($logger);

// send a request with the payload requested by the api.
$client->request('PATCH', '/api/_action/axro_product_extension/update', $payload);

// to return a object instead of an array, set 4th argument to true
$client->request('PATCH', '/api/_action/axro_product_extension/update', $payload, true);
$client->request('GET', 'api/product/', [], true);

```

### Async Requests ###

```
use AxroShopware\Client\ShopwareClient;

// Create an instance with your credentials
$client = new ShopwareClient(
    $baseUrl, $clientId, $clientSecret
);

// add your monolog logger LoggerInterface
$client->setLogger($logger);

// send a request with the payload requested by the api.
foreach(something...) {
    // create $payload with your data from foreach etc.
    $client->requestAsync('PATCH', '/api/_action/axro_product_extension/update', $payload);
}
$responses = $client->promise();

// to return objects in numeric array instead of an array, set argument to true
$responses = $client->promise(true);
```

You can use the HTTP methods: GET, POST, PATCH, PUT, DELETE

### Indexing ###
Indexing is set by default to "use-queue-indexing".

You can change it to synchronously or disable.

```
$client->indexing(const::INDEXING_SYNC)->requestAsync('PATCH', '/api/_action/axro_product_extension/update', $payload);
```

Following constants are defined for sync behavior in ShopwareClient:

```
INDEXING_SYNC    => Data will be indexed synchronously
INDEXING_QUEUE   => Data will be indexed asynchronously
INDEXING_DISABLE => Data indexing is completely disabled
```
