<?php

namespace Tests\Concerns;

use App\Models\MainDomain;
use Illuminate\Testing\TestResponse;

trait ActsAsSuperAdmin
{
    protected ?MainDomain $mainDomain = null;

    protected function setUpMainDomain(): MainDomain
    {
        return $this->mainDomain = MainDomain::factory()->create([
            'domain' => 'salonflow.test',
        ]);
    }

    protected function superAdminUrl(string $uri): string
    {
        return 'http://admin.salonflow.test'.$uri;
    }

    protected function getFromSuperAdmin(string $uri): TestResponse
    {
        return $this->get($this->superAdminUrl($uri));
    }

    protected function postToSuperAdmin(string $uri, array $data = []): TestResponse
    {
        return $this->post($this->superAdminUrl($uri), $data);
    }

    protected function putToSuperAdmin(string $uri, array $data = []): TestResponse
    {
        return $this->put($this->superAdminUrl($uri), $data);
    }

    protected function deleteFromSuperAdmin(string $uri): TestResponse
    {
        return $this->delete($this->superAdminUrl($uri));
    }
}
