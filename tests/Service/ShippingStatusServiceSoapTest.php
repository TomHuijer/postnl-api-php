<?php
/**
 * The MIT License (MIT).
 *
 * Copyright (c) 2017-2021 Michael Dekker
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
 * associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute,
 * sublicense, and/or sell copies of the Software, and to permit persons to whom the Software
 * is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or
 * substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT
 * NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author    Michael Dekker <git@michaeldekker.nl>
 * @copyright 2017-2021 Michael Dekker
 * @license   https://opensource.org/licenses/MIT The MIT License
 */

namespace ThirtyBees\PostNL\Tests\Service;

use Cache\Adapter\Void\VoidCachePool;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Message as PsrMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ThirtyBees\PostNL\Entity\Address;
use ThirtyBees\PostNL\Entity\Customer;
use ThirtyBees\PostNL\Entity\Message\Message;
use ThirtyBees\PostNL\Entity\Request\CompleteStatus;
use ThirtyBees\PostNL\Entity\Request\CurrentStatus;
use ThirtyBees\PostNL\Entity\Request\GetSignature;
use ThirtyBees\PostNL\Entity\Response\CompleteStatusResponse;
use ThirtyBees\PostNL\Entity\Response\CurrentStatusResponse;
use ThirtyBees\PostNL\Entity\Response\GetSignatureResponseSignature;
use ThirtyBees\PostNL\Entity\Shipment;
use ThirtyBees\PostNL\Entity\SOAP\UsernameToken;
use ThirtyBees\PostNL\HttpClient\MockClient;
use ThirtyBees\PostNL\PostNL;
use ThirtyBees\PostNL\Service\ShippingStatusServiceInterface;
use function count;

/**
 * Class ShippingStatusRestTest.
 *
 * @testdox The ShippingStatusService (SOAP)
 */
class ShippingStatusServiceSoapTest extends TestCase
{
    /** @var PostNL */
    protected $postnl;
    /** @var ShippingStatusServiceInterface */
    protected $service;
    /** @var */
    protected $lastRequest;

    /**
     * @before
     *
     * @throws \ThirtyBees\PostNL\Exception\InvalidArgumentException
     */
    public function setupPostNL()
    {
        $this->postnl = new PostNL(
            Customer::create()
                ->setCollectionLocation('123456')
                ->setCustomerCode('DEVC')
                ->setCustomerNumber('11223344')
                ->setContactPerson('Test')
                ->setAddress(Address::create([
                    'AddressType' => '02',
                    'City'        => 'Hoofddorp',
                    'CompanyName' => 'PostNL',
                    'Countrycode' => 'NL',
                    'HouseNr'     => '42',
                    'Street'      => 'Siriusdreef',
                    'Zipcode'     => '2132WT',
                ]))
                ->setGlobalPackBarcodeType('AB')
                ->setGlobalPackCustomerCode('1234'), new UsernameToken(null, 'test'),
            true,
            PostNL::MODE_SOAP
        );

        $this->service = $this->postnl->getShippingStatusService();
        $this->service->cache = new VoidCachePool();
        $this->service->ttl = 1;
    }

    /**
     * @after
     */
    public function logPendingRequest()
    {
        if (!$this->lastRequest instanceof Request) {
            return;
        }

        global $logger;
        if ($logger instanceof LoggerInterface) {
            $logger->debug($this->getName()." Request\n".\GuzzleHttp\Psr7\str($this->lastRequest));
        }
        $this->lastRequest = null;
    }

    /**
     * @testdox creates a valid CurrentStatus request
     */
    public function testGetCurrentStatusRequestSoap()
    {
        $barcode = '3SDEVC201611210';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCurrentStatusRequest(
            (new CurrentStatus())
                ->setShipment(
                    (new Shipment())
                        ->setBarcode($barcode)
                )
                ->setMessage($message)
        );

        $query = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());

        $this->assertEmpty($query);
        $this->assertEquals('test', $request->getHeaderLine('apikey'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals("/shipment/v2/status/barcode/$barcode", $request->getUri()->getPath());
    }

    /**
     * @testdox can get the current status
     */
    public function testGetCurrentStatusSoap()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json;charset=UTF-8'], '{
  "CurrentStatus": {
    "Shipment": {
      "MainBarcode": "3SDEVC302392342",
      "Barcode": "3SDEVC302392342",
      "ShipmentAmount": "1",
      "ShipmentCounter": "1",
      "Customer": {
        "CustomerCode": "DEVC",
        "CustomerNumber": "11223344",
        "Name": "kja fasdfasdf"
      },
      "ProductCode": "003089",
      "ProductDescription": "Handtek. voor Ontvangst\/Alleen huisadres",
      "Reference": "SK123456",
      "Dimension": {
        "Height": "110",
        "Length": "255",
        "Volume": "5330",
        "Weight": "260",
        "Width": "190"
      },
      "Address": [
        {
          "AddressType": "01",
          "Building": {},
          "City": "Nijmegen",
          "CompanyName": {},
          "CountryCode": "NL",
          "DepartmentName": {},
          "District": {},
          "FirstName": "A",
          "Floor": {},
          "HouseNumber": "12",
          "HouseNumberSuffix": {},
          "LastName": "B",
          "Region": {},
          "Remark": {},
          "Street": "Ergens",
          "Zipcode": "1234GN"
        },
        {
          "AddressType": "02",
          "Building": {},
          "City": "Hoofddorp",
          "CompanyName": "PostNL",
          "CountryCode": "NL",
          "DepartmentName": {},
          "District": {},
          "FirstName": {},
          "Floor": {},
          "HouseNumber": "42",
          "HouseNumberSuffix": {},
          "LastName": {},
          "Region": {},
          "Remark": {},
          "Street": "Siriusdreef",
          "Zipcode": "3212WT"
        }
      ],
      "Status": {
        "TimeStamp": "07-03-2018 23:20:05",
        "StatusCode": "2",
        "StatusDescription": "Zending in ontvangst genomen",
        "PhaseCode": "1",
        "PhaseDescription": "Collectie"
      }
    }
  }
}
'), ]);
        $handler = HandlerStack::create($mock);
        $mockClient = new MockClient();
        $mockClient->setHandler($handler);
        $this->postnl->setHttpClient($mockClient);

        $currentStatusResponse = $this->postnl->getCurrentStatus(
            (new CurrentStatus())
                ->setShipment(
                    (new Shipment())
                        ->setBarcode('3S8392302392342')
                )
        );

        $this->assertInstanceOf(CurrentStatusResponse::class, $currentStatusResponse);
    }

    /**
     * @testdox creates a valid CurrentStatusByReference request
     */
    public function testGetCurrentStatusByReferenceRequestSoap()
    {
        $reference = '339820938';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCurrentStatusRequest(
            (new CurrentStatus())
                ->setShipment(
                    (new Shipment())
                        ->setReference($reference)
                )
                ->setMessage($message)
        );

        $query = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());

        $this->assertEquals([
            'customerCode'   => $this->postnl->getCustomer()->getCustomerCode(),
            'customerNumber' => $this->postnl->getCustomer()->getCustomerNumber(),
        ], $query);
        $this->assertEquals('test', $request->getHeaderLine('apikey'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals("/shipment/v2/status/reference/$reference", $request->getUri()->getPath());
    }

    /**
     * @testdox creates a valid CurrentStatusByStatus request
     */
    public function testGetCurrentStatusByStatusRequestSoap()
    {
        $status = '1';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCurrentStatusRequest(
            (new CurrentStatus())
                ->setShipment(
                    (new Shipment())
                        ->setStatusCode($status)
                )
                ->setMessage($message)
        );

        $query = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());

        $this->assertEquals([
            'customerCode'   => $this->postnl->getCustomer()->getCustomerCode(),
            'customerNumber' => $this->postnl->getCustomer()->getCustomerNumber(),
            'status'         => $status,
        ], $query);
        $this->assertEquals('test', $request->getHeaderLine('apikey'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals('/shipment/v2/status/search', $request->getUri()->getPath());
    }

    /**
     * @testdox creates a valid CompleteStatus request
     */
    public function testGetCompleteStatusRequestSoap()
    {
        $barcode = '3SDEVC201611210';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCompleteStatusRequest(
            (new CompleteStatus())
                ->setShipment(
                    (new Shipment())
                        ->setBarcode($barcode)
                )
                ->setMessage($message)
        );

        $query = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());

        $this->assertEquals([
            'detail' => 'true',
        ], $query);
        $this->assertEquals('test', $request->getHeaderLine('apikey'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals("/shipment/v2/status/barcode/$barcode", $request->getUri()->getPath());
    }

    /**
     * @testdox can retrieve the complete status
     */
    public function testGetCompleteStatusSoap()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json;charset=UTF-8'], '{
  "CompleteStatus": {
    "Shipment": {
      "MainBarcode": "33DEVC123456789",
      "Barcode": "3SDEVC123456789",
      "ShipmentAmount": "1",
      "ShipmentCounter": "1",
      "Customer": {
        "CustomerCode": "DEVC",
        "CustomerNumber": "11223344",
        "Name": "ASDFASDFASDFASFDD"
      },
      "ProductCode": "003089",
      "ProductDescription": "Handtek. voor Ontvangst\/Alleen huisadres",
      "Reference": "DF00DF91",
      "Dimension": {
        "Height": "110",
        "Length": "255",
        "Volume": "5330",
        "Weight": "260",
        "Width": "190"
      },
      "Addresses": [
        {
          "AddressType": "01",
          "Building": {},
          "City": "Nijmegen",
          "CompanyName": {},
          "CountryCode": "NL",
          "DepartmentName": {},
          "District": {},
          "FirstName": "SADFASDF",
          "Floor": {},
          "HouseNumber": "12",
          "HouseNumberSuffix": {},
          "LastName": "SAD ASDF",
          "Region": {},
          "Remark": {},
          "Street": "ASDFDFDFD",
          "Zipcode": "1234DF"
        },
        {
          "AddressType": "02",
          "Building": {},
          "City": "ASDFD DF ASDFDFDFD",
          "CompanyName": "ASD ASDFASDF DF",
          "CountryCode": "NL",
          "DepartmentName": {},
          "District": {},
          "FirstName": {},
          "Floor": {},
          "HouseNumber": "7",
          "HouseNumberSuffix": {},
          "LastName": {},
          "Region": {},
          "Remark": {},
          "Street": "ASDFASDFASDD ASDFASDFASDF",
          "Zipcode": "1234DF"
        }
      ],
      "Event": [
        {
          "Code": "01B",
          "Description": "Zending is bij PostNL",
          "DestinationLocationCode": "156731",
          "LocationCode": "166886",
          "RouteCode": "419",
          "RouteName": "419 Nm-Altrada",
          "TimeStamp": "07-03-2018 23:20:05"
        },
        {
          "Code": "01A",
          "Description": "Zending wordt verwacht, maar zit nog niet in sorteerproces",
          "DestinationLocationCode": {},
          "LocationCode": "888888",
          "RouteCode": {},
          "RouteName": {},
          "TimeStamp": "07-03-2018 09:51:08"
        },
        {
          "Code": "01A",
          "Description": "Zending wordt verwacht, maar zit nog niet in sorteerproces",
          "DestinationLocationCode": {},
          "LocationCode": "888888",
          "RouteCode": {},
          "RouteName": {},
          "TimeStamp": "07-03-2018 09:50:47"
        }
      ],
      "Expectation": {
        "ETAFrom": "2018-03-08T11:30:00",
        "ETATo": "2018-03-08T14:00:00"
      },
      "Status": {
        "TimeStamp": "07-03-2018 23:20:05",
        "StatusCode": "2",
        "StatusDescription": "Zending in ontvangst genomen",
        "PhaseCode": "1",
        "PhaseDescription": "Collectie"
      },
      "OldStatus": [
        {
          "TimeStamp": "07-03-2018 23:22:56.326",
          "StatusCode": "99",
          "StatusDescription": "Niet van toepassing",
          "PhaseCode": "99",
          "PhaseDescription": "Niet van toepassing"
        },
        {
          "TimeStamp": "07-03-2018 23:20:05",
          "StatusCode": "2",
          "StatusDescription": "Zending in ontvangst genomen",
          "PhaseCode": "1",
          "PhaseDescription": "Collectie"
        },
        {
          "TimeStamp": "07-03-2018 09:55:35.976",
          "StatusCode": "99",
          "StatusDescription": "Niet van toepassing",
          "PhaseCode": "99",
          "PhaseDescription": "Niet van toepassing"
        },
        {
          "TimeStamp": "07-03-2018 09:51:08",
          "StatusCode": "1",
          "StatusDescription": "Zending voorgemeld",
          "PhaseCode": "1",
          "PhaseDescription": "Collectie"
        },
        {
          "TimeStamp": "07-03-2018 09:50:47",
          "StatusCode": "1",
          "StatusDescription": "Zending voorgemeld",
          "PhaseCode": "1",
          "PhaseDescription": "Collectie"
        }
      ]
    }
  }
}
'), ]);
        $handler = HandlerStack::create($mock);
        $mockClient = new MockClient();
        $mockClient->setHandler($handler);
        $this->postnl->setHttpClient($mockClient);

        $completeStatusResponse = $this->postnl->getCompleteStatus(
            (new CompleteStatus())
                ->setShipment(
                    (new Shipment())
                        ->setBarcode('3SABCD6659149')
                )
        );

        $this->assertInstanceOf(CompleteStatusResponse::class, $completeStatusResponse);
        $this->assertEquals(2, count($completeStatusResponse->getShipments()[0]->getAddresses()));
        $this->assertNull($completeStatusResponse->getShipments()[0]->getAmounts());
        $this->assertEquals(3, count($completeStatusResponse->getShipments()[0]->getEvents()));
        $this->assertNull($completeStatusResponse->getShipments()[0]->getGroups());
        $this->assertInstanceOf(Customer::class, $completeStatusResponse->getShipments()[0]->getCustomer());
        $this->assertEquals('07-03-2018 09:50:47', $completeStatusResponse->getShipments()[0]->getOldStatuses()[4]->getTimeStamp());
    }

    /**
     * @testdox creates a valid CompleteStatusByReference request
     */
    public function testGetCompleteStatusByReferenceRequestSoap()
    {
        $reference = '339820938';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCompleteStatusRequest(
            (new CompleteStatus())
                ->setShipment(
                    (new Shipment())
                        ->setReference($reference)
                )
                ->setMessage($message)
        );

        $query = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());

        $this->assertEquals([
            'customerCode'   => $this->postnl->getCustomer()->getCustomerCode(),
            'customerNumber' => $this->postnl->getCustomer()->getCustomerNumber(),
            'detail'         => 'true',
        ], $query);
        $this->assertEquals('test', $request->getHeaderLine('apikey'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals("/shipment/v2/status/reference/$reference", $request->getUri()->getPath());
    }

    /**
     * @testdox creates a valid CompleteStatusByStatus request
     */
    public function testGetCompleteStatusByStatusRequestSoap()
    {
        $status = '1';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildCompleteStatusRequest(
            (new CompleteStatus())
                ->setShipment(
                    (new Shipment())
                        ->setStatusCode($status)
                        ->setDateFrom('29-06-2016')
                        ->setDateTo('20-07-2016')
                )
                ->setMessage($message)
        );

        $query = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());

        $this->assertEquals([
            'customerCode'   => $this->postnl->getCustomer()->getCustomerCode(),
            'customerNumber' => $this->postnl->getCustomer()->getCustomerNumber(),
            'status'         => $status,
            'detail'         => 'true',
            'startDate'      => '29-06-2016',
            'endDate'        => '20-07-2016',
        ], $query);
        $this->assertEquals('test', $request->getHeaderLine('apikey'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals('/shipment/v2/status/search', $request->getUri()->getPath());
    }

    /**
     * @testdox creates a valid GetSignature request
     */
    public function testGetSignatureRequestSoap()
    {
        $barcode = '3S9283920398234';
        $message = new Message();

        $this->lastRequest = $request = $this->service->buildGetSignatureRequest(
            (new GetSignature())
                ->setCustomer($this->postnl->getCustomer())
                ->setMessage($message)
                ->setShipment((new Shipment())
                    ->setBarcode($barcode)
                )
        );

        $query = \GuzzleHttp\Psr7\parse_query($request->getUri()->getQuery());

        $this->assertEmpty($query);
        $this->assertEquals('test', $request->getHeaderLine('apikey'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));
        $this->assertEquals("/shipment/v2/status/signature/$barcode", $request->getUri()->getPath());
    }

    /**
     * @testdox can get the signature
     */
    public function testGetSignatureSoap()
    {
        $mock = new MockHandler([

        ]);
        $handler = HandlerStack::create($mock);
        $mockClient = new MockClient();
        $mockClient->setHandler($handler);
        $this->postnl->setHttpClient($mockClient);

        $signatureResponse = $this->postnl->getSignature(
            (new GetSignature())
                ->setShipment((new Shipment())
                    ->setBarcode('3SABCD6659149')
                )
        );

        $this->assertInstanceOf(GetSignatureResponseSignature::class, $signatureResponse);
    }
}
