<?php

namespace App\tests\controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();

        // Send a GET request to the index method of BookController
        $client->request('GET', '/api/books/');

        $response = $client->getResponse();

        // Debug: Print response details
        dump([
            'status_code' => $response->getStatusCode(),
            'content' => $response->getContent(),
            'headers' => $response->headers->all()
        ]);

        // Check the returned HTTP status code
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        // Check if the returned content is JSON
        $this->assertJson($response->getContent());

        // Check the returned JSON data
        $data = json_decode($response->getContent(), true);

        // Print data for debugging
        dump($data);

        $this->assertIsArray($data);
        $this->assertNotEmpty($data, "No books were returned");
        $this->assertArrayHasKey(0, $data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('title', $data[0]);
    }

    
}