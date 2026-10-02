<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeamMessagesRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_message_data_and_presence_column_are_removed_from_the_schema(): void
    {
        $this->assertFalse(Schema::hasTable('direct_messages'));
        $this->assertFalse(Schema::hasColumn('users', 'last_seen_at'));
    }

    public function test_team_message_pages_and_api_routes_are_unavailable(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/messages')->assertNotFound();
        $this->getJson('/messages/users')->assertNotFound();
    }
}
