<?php

namespace Tests\Feature;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopupMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_flash_and_named_validation_errors_are_in_popup_payload(): void
    {
        session()->flash('success', 'Import finished');
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['file' => 'Invalid barcode']))
            ->put('updatePassword', new MessageBag(['password' => 'Password is required']));
        $html = view('layouts.popup-messages', compact('errors'))->render();
        $this->assertStringContainsString('Import finished', $html);
        $this->assertStringContainsString('Invalid barcode', $html);
        $this->assertStringContainsString('Password is required', $html);
        $this->assertStringContainsString('AppAlert.show', $html);
        $this->assertStringContainsString('window.alert(message.text)', $html);
        $this->assertStringContainsString('showMessages();', $html);
        $this->assertStringNotContainsString('alert-danger', $html);
    }
}
