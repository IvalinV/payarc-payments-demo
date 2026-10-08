<?php

namespace App\Ship\Seeders;

use App\Ship\Parents\Seeders\Seeder;
use App\Containers\AppSection\Authorization\Models\Permission;
use App\Containers\AppSection\Authorization\Models\Role;
use App\Containers\AppSection\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class InitialDeploymentDataSeeder extends Seeder
{
    /**
     * Note: This seeder is not loaded automatically by Apiato
     * This is a special seeder that can be called by "apiato:seed-deploy" command
     * It is useful for seeding data for initial deployment.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $roles = $this->seedAuthorization();
            $users = $this->seedUsers($roles);
            [$agents, $accounts] = $this->seedAccounts($users);
            $this->seedCharges($users, $accounts);
            $this->seedAgentUsers($users, $agents);
        });
    }

    /**
     * @return array<string, Role>
     */
    private function seedAuthorization(): array
    {
        $permissionNames = [
            'admin.access',
            'charges.view',
            'charges.create',
            'charges.capture',
            'charges.refund',
            'charges.void',
            'merchants.manage',
            'agents.manage',
            'users.manage',
        ];

        $permissions = [];
        foreach ($permissionNames as $name) {
            $permissions[$name] = Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
            );
        }

        $rolePermissions = [
            'admin' => $permissionNames,
            'merchant' => ['charges.view', 'charges.create', 'charges.capture', 'charges.refund', 'charges.void'],
            'agent' => ['charges.view'],
        ];

        $roles = [];
        foreach ($rolePermissions as $name => $names) {
            $role = Role::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['display_name' => ucfirst($name)],
            );
            $role->syncPermissions(array_map(static fn (string $permission): Permission => $permissions[$permission], $names));
            $roles[$name] = $role;
        }

        return $roles;
    }

    /**
     * @param array<string, Role> $roles
     * @return array<string, User>
     */
    private function seedUsers(array $roles): array
    {
        $definitions = [
            'admin@payarc.test' => ['name' => 'Demo Admin', 'role' => 'admin'],
            'agent1@payarc.test' => ['name' => 'Agent One', 'role' => 'agent'],
            'agent2@payarc.test' => ['name' => 'Agent Two', 'role' => 'agent'],
            'merchant1@payarc.test' => ['name' => 'Merchant One', 'role' => 'merchant'],
            'merchant2@payarc.test' => ['name' => 'Merchant Two', 'role' => 'merchant'],
            'merchant3@payarc.test' => ['name' => 'Merchant Three', 'role' => 'merchant'],
            'norole@payarc.test' => ['name' => 'No Role', 'role' => null],
        ];

        $users = [];
        foreach ($definitions as $email => $definition) {
            DB::table('users')->updateOrInsert(
                ['email' => $email],
                [
                    'name' => $definition['name'],
                    'password' => Hash::make('Demo#12345'),
                    'is_admin' => $definition['role'] === 'admin',
                    'is_agent' => $definition['role'] === 'agent',
                    'is_merchant' => $definition['role'] === 'merchant',
                    'email_verified_at' => CarbonImmutable::create(2025, 1, 1, 0, 0, 0, 'UTC'),
                    'updated_at' => now(),
                    'created_at' => CarbonImmutable::create(2025, 1, 1, 0, 0, 0, 'UTC'),
                ],
            );

            $user = User::query()->where('email', $email)->firstOrFail();
            $user->syncRoles($definition['role'] === null ? [] : [$roles[$definition['role']]]);
            $users[$email] = $user->fresh();
        }

        return $users;
    }

    /**
     * @param array<string, User> $users
     * @return array{array<string, int>, array<int, int>}
     */
    private function seedAccounts(array $users): array
    {
        $agentDefinitions = [
            'agent1@payarc.test' => ['first_name' => 'Agent', 'last_name' => 'One', 'company' => 'Agent One LLC', 'type' => 'parent', 'split' => 50.00],
            'agent2@payarc.test' => ['first_name' => 'Agent', 'last_name' => 'Two', 'company' => 'Agent Two LLC', 'type' => 'child', 'split' => 25.00],
        ];

        $agents = [];
        foreach ($agentDefinitions as $email => $definition) {
            DB::table('agents')->updateOrInsert(
                ['email' => $email],
                [
                    'first_name' => $definition['first_name'],
                    'last_name' => $definition['last_name'],
                    'company_legal_name' => $definition['company'],
                    'company_dba' => $definition['company'],
                    'type' => $definition['type'],
                    'default_split' => $definition['split'],
                    'parent_agent_id' => $email === 'agent2@payarc.test' ? DB::table('agents')->where('email', 'agent1@payarc.test')->value('id') : null,
                    'updated_at' => now(),
                    'created_at' => CarbonImmutable::create(2025, 1, 1, 0, 0, 0, 'UTC'),
                ],
            );
            $agents[$email] = (int) DB::table('agents')->where('email', $email)->value('id');
        }

        $accountDefinitions = [
            1 => ['name' => 'Merchant One Account', 'merchant' => 'merchant1@payarc.test', 'status' => 'active', 'active' => true],
            2 => ['name' => 'Merchant Two Main', 'merchant' => 'merchant2@payarc.test', 'status' => 'active', 'active' => true],
            3 => ['name' => 'Merchant Two Reserve', 'merchant' => 'merchant2@payarc.test', 'status' => 'active', 'active' => true],
            4 => ['name' => 'Merchant Three Account', 'merchant' => 'merchant3@payarc.test', 'status' => 'active', 'active' => true],
            5 => ['name' => 'Agent Managed Account', 'merchant' => null, 'status' => 'active', 'active' => true],
            6 => ['name' => 'Suspended Account', 'merchant' => null, 'status' => 'suspended', 'active' => false],
        ];

        $accounts = [];
        foreach ($accountDefinitions as $number => $definition) {
            $mid = sprintf('MID_DEMO_%03d', $number);
            DB::table('accounts')->updateOrInsert(
                ['mid' => $mid],
                [
                    'business_name' => $definition['name'],
                    'dba_name' => $definition['name'],
                    'legal_name' => $definition['name'] . ' LLC',
                    'email' => $definition['merchant'] ?? 'account' . $number . '@payarc.test',
                    'status' => $definition['status'],
                    'active_yn' => $definition['active'],
                    'created_by' => $users['admin@payarc.test']->id,
                    'updated_at' => now(),
                    'created_at' => CarbonImmutable::create(2025, 1, 1, 0, 0, 0, 'UTC'),
                ],
            );

            $accountId = (int) DB::table('accounts')->where('mid', $mid)->value('id');
            $accounts[$number] = $accountId;

            if ($definition['merchant'] !== null) {
                DB::table('account_user')->updateOrInsert([
                    'account_id' => $accountId,
                    'user_id' => $users[$definition['merchant']]->id,
                ]);
            }
        }

        foreach ([1 => ['agent1@payarc.test'], 2 => ['agent1@payarc.test', 'agent2@payarc.test'], 3 => ['agent2@payarc.test'], 4 => ['agent1@payarc.test']] as $number => $agentEmails) {
            foreach ($agentEmails as $email) {
                DB::table('agent_account')->updateOrInsert([
                    'agent_id' => $agents[$email],
                    'account_id' => $accounts[$number],
                ]);
            }
        }

        return [$agents, $accounts];
    }

    /**
     * @param array<string, User> $users
     * @param array<int, int> $accounts
     */
    private function seedCharges(array $users, array $accounts): void
    {
        $start = CarbonImmutable::create(2025, 1, 1, 0, 0, 0, 'UTC');
        $statuses = ['authorized', 'captured', 'refunded', 'voided', 'failed'];

        for ($number = 1; $number <= 300; $number++) {
            $status = $statuses[$number % count($statuses)];
            $amount = 1000 + ($number * 100);
            $captured = in_array($status, ['captured', 'refunded'], true) ? $amount : 0;
            $refunded = $status === 'refunded' ? $captured : ($status === 'captured' && $number % 10 === 1 ? intdiv($captured, 2) : 0);
            $voided = $status === 'voided' ? $amount : 0;
            $chargeStatus = $status === 'captured' && $refunded === $captured ? 'refunded' : $status;
            $accountNumber = (($number - 1) % count($accounts)) + 1;

            DB::table('charges')->updateOrInsert(
                ['external_transaction_id' => sprintf('txn_demo_%06d', $number)],
                [
                    'account_id' => $accounts[$accountNumber],
                    'amount' => $amount,
                    'amount_captured' => $captured,
                    'amount_refunded' => $refunded,
                    'amount_voided' => $voided,
                    'currency' => ['USD', 'EUR', 'GBP', 'BGN'][$number % 4],
                    'status' => $chargeStatus,
                    'customer_email' => sprintf('customer%03d@example.test', $number),
                    'refunded' => $refunded > 0,
                    'disputed' => $number % 17 === 0,
                    'void_reason' => $status === 'voided' ? 'Demo void' : null,
                    'description' => 'Deterministic demo charge ' . $number,
                    'idempotency_key' => sprintf('idem_demo_%06d', $number),
                    'created_by' => $users['admin@payarc.test']->id,
                    'created_at' => $start->addDays(intdiv(($number - 1) * 89, 299))->addHours($number % 24),
                    'updated_at' => $start->addDays(intdiv(($number - 1) * 89, 299))->addHours($number % 24),
                ],
            );
        }
    }

    /**
     * @param array<string, User> $users
     * @param array<string, int> $agents
     */
    private function seedAgentUsers(array $users, array $agents): void
    {
        foreach (['agent1@payarc.test', 'agent2@payarc.test'] as $email) {
            DB::table('agent_user')->updateOrInsert([
                'agent_id' => $agents[$email],
                'user_id' => $users[$email]->id,
            ]);
        }
    }
}
