<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdSenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_ads_txt_declara_o_editor(): void
    {
        $this->assertSame(
            'google.com, pub-8311618945249138, DIRECT, f08c47fec0942fa0',
            trim(file_get_contents(public_path('ads.txt')))
        );
    }

    public function test_script_aparece_nas_paginas_publicas_em_producao(): void
    {
        $this->app['env'] = 'production';

        $this->get('/normas-abnt')
            ->assertOk()
            ->assertSee('pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8311618945249138', false)
            ->assertSee('<meta name="google-adsense-account" content="ca-pub-8311618945249138">', false);
    }

    public function test_script_nao_carrega_fora_de_producao(): void
    {
        $this->get('/normas-abnt')
            ->assertOk()
            ->assertDontSee('adsbygoogle.js', false);
    }
}
