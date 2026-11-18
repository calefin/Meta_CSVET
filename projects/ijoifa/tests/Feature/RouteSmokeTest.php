<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
    }

    private function iniciado(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'iniciado', 'status' => 'active']);
    }

    private function consulente(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);
    }

    public static function publicRoutes(): array
    {
        return [
            ['home'],
            ['privacy'],
            ['register'],
            ['login'],
            ['password.request'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicRoutes')]
    public function test_public_route_is_reachable_without_auth(string $route): void
    {
        $this->get(route($route))->assertOk();
    }

    public static function guestOnlyRoutes(): array
    {
        return [
            ['register'],
            ['login'],
            ['password.request'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('guestOnlyRoutes')]
    public function test_guest_only_route_redirects_when_already_authenticated(string $route): void
    {
        $this->actingAs($this->consulente())->get(route($route))->assertRedirect();
    }

    public static function adminRoutes(): array
    {
        return [
            ['admin.dashboard'],
            ['admin.agenda'],
            ['admin.notes'],
            ['admin.approvals'],
            ['admin.audit-log'],
            ['admin.announcements'],
            ['admin.events'],
            ['admin.backups'],
            ['admin.catalogs.animals'],
            ['admin.catalogs.suppliers'],
            ['admin.catalogs.ibi-ire'],
            ['admin.catalogs.supplies'],
            ['admin.catalogs.odus'],
            ['admin.catalogs.ritual-works'],
            ['admin.courses.index'],
            ['admin.courses.quizzes'],
            ['admin.deletion-requests'],
            ['admin.finance.index'],
            ['admin.finance.prices'],
            ['admin.messages'],
            ['admin.oracular.queue'],
            ['admin.oracular.simulator'],
            ['admin.users'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminRoutes')]
    public function test_admin_route_is_reachable_by_admin(string $route): void
    {
        $this->actingAs($this->admin())->get(route($route))->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminRoutes')]
    public function test_admin_route_is_forbidden_to_consulente(string $route): void
    {
        $this->actingAs($this->consulente())->get(route($route))->assertForbidden();
    }

    public static function iniciadoRoutes(): array
    {
        return [
            ['iniciado.dashboard'],
            ['iniciado.messages'],
            ['iniciado.initiation'],
            ['iniciado.family'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('iniciadoRoutes')]
    public function test_iniciado_route_is_reachable_by_iniciado(string $route): void
    {
        $this->actingAs($this->iniciado())->get(route($route))->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('iniciadoRoutes')]
    public function test_iniciado_route_is_forbidden_to_consulente(string $route): void
    {
        $this->actingAs($this->consulente())->get(route($route))->assertForbidden();
    }

    public static function consulenteRoutes(): array
    {
        return [
            ['consulente.dashboard'],
            ['consulente.consultations'],
            ['consulente.appointments'],
            ['consulente.messages'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('consulenteRoutes')]
    public function test_consulente_route_is_reachable_by_consulente(string $route): void
    {
        $this->actingAs($this->consulente())->get(route($route))->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('consulenteRoutes')]
    public function test_consulente_route_is_forbidden_to_iniciado(string $route): void
    {
        $this->actingAs($this->iniciado())->get(route($route))->assertForbidden();
    }

    public static function sharedRoutes(): array
    {
        return [
            ['profile'],
            ['my-data'],
            ['my-data.export.json'],
            ['my-data.export.pdf'],
            ['student.courses.index'],
            ['ose-calendar'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sharedRoutes')]
    public function test_shared_route_is_reachable_by_any_active_role(string $route): void
    {
        $this->actingAs($this->consulente())->get(route($route))->assertOk();
        $this->actingAs($this->iniciado())->get(route($route))->assertOk();
    }

    public function test_protected_routes_redirect_guests_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('profile'))->assertRedirect(route('login'));
    }

    public function test_pending_user_is_blocked_from_protected_routes(): void
    {
        $user = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'pending']);

        $this->actingAs($user)->get(route('consulente.dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_can_view_initiation_manager_for_a_specific_iniciado(): void
    {
        $admin = $this->admin();
        $iniciado = $this->iniciado();

        $this->actingAs($admin)
            ->get(route('admin.initiations.show', $iniciado))
            ->assertOk();
    }

    public function test_admin_can_view_admin_notes_for_a_specific_user(): void
    {
        $admin = $this->admin();
        $target = $this->consulente();

        $this->actingAs($admin)
            ->get(route('admin.notes.user', $target->id))
            ->assertOk();
    }
}
