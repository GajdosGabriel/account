<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Prvý operátor back-office. Aplikácia nemá verejnú registráciu
 * (/register neexistuje), takže bez tohto seedera sa do nej nedá dostať.
 *
 *   php artisan db:seed --class=AdminUserSeeder
 *
 * Dá sa spustiť opakovane – existujúcemu účtu prepíše heslo a vytiahne ho
 * z koša, takže slúži aj ako núdzové obnovenie prístupu.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('accounts.seed_admin.email');
        $password = config('accounts.seed_admin.password');

        if (! $email || ! $password) {
            throw new RuntimeException(
                'Chýba SEED_ADMIN_EMAIL alebo SEED_ADMIN_PASSWORD v .env. '
                .'Po zmene .env na serveri spusti `php artisan config:clear`.'
            );
        }

        // V .env má byť bcrypt hash, nie čitateľné heslo – cast 'hashed' hotový
        // hash pustí ďalej nezmenený a zahashuje len obyčajný text. Nový hash:
        //   php artisan tinker --execute="echo bcrypt('tvoje-heslo');"
        $prehashovane = Hash::isHashed($password);

        // User používa SoftDeletes: bez withTrashed() by sa zmazaný účet
        // nenašiel a insert by spadol na unikátnom e-maile.
        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $existed = $user->exists;

        $user->forceFill([
            'name' => config('accounts.seed_admin.name'),
            'password' => $password,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'deleted_at' => null,
        ])->save();

        $this->command?->info(($existed ? 'Heslo prepísané: ' : 'Účet vytvorený: ').$email
            .($prehashovane ? ' (heslo z hashu v .env)' : " / {$password}"));

        if (! $prehashovane) {
            $this->command?->warn('SEED_ADMIN_PASSWORD je v .env čitateľné. Odporúčam nahradiť ho bcrypt hashom.');
        }
    }
}
