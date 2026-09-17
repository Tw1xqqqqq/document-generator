<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Демонстрационные организации.
 * Нужны, чтобы после запуска сервис сразу можно было потрогать,
 * не заполняя реквизиты руками.
 */
class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = [
            [
                'name' => 'ООО «Ромашка»',
                'full_name' => 'Общество с ограниченной ответственностью «Ромашка»',
                'inn' => '7701234567',
                'kpp' => '770101001',
                'ogrn' => '1157746123456',
                'legal_address' => '101000, г. Москва, ул. Мясницкая, д. 15, оф. 401',
                'actual_address' => '101000, г. Москва, ул. Мясницкая, д. 15, оф. 401',
                'phone' => '+7 (495) 123-45-67',
                'email' => 'info@romashka.example',
                'bank_name' => 'ПАО «Сбербанк»',
                'bank_account' => '40702810400000012345',
                'bank_corr_account' => '30101810400000000225',
                'bank_bik' => '044525225',
                'director_name' => 'Иванов Иван Иванович',
                'director_position' => 'Генеральный директор',
            ],
            [
                'name' => 'ИП Петров П. П.',
                'full_name' => 'Индивидуальный предприниматель Петров Пётр Петрович',
                'inn' => '780112345678',
                'ogrn' => '316784700123456',
                'legal_address' => '190000, г. Санкт-Петербург, наб. реки Мойки, д. 10',
                'phone' => '+7 (812) 987-65-43',
                'email' => 'petrov@example.com',
                'bank_name' => 'АО «Альфа-Банк»',
                'bank_account' => '40802810300000054321',
                'bank_corr_account' => '30101810200000000593',
                'bank_bik' => '044525593',
                'director_name' => 'Петров Пётр Петрович',
                'director_position' => 'Индивидуальный предприниматель',
            ],
        ];

        foreach ($organizations as $data) {
            // updateOrCreate, чтобы повторный запуск не плодил дубликаты
            Organization::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}
