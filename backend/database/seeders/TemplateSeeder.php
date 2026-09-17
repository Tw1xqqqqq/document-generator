<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Services\Templates\TemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Демонстрационные шаблоны: счёт, акт и договор.
 *
 * Файлы docx не лежат в репозитории, а собираются кодом при первом запуске.
 * Так сервис сразу после `docker compose up` можно попробовать в деле,
 * и видно, что формат меток ${...} действительно работает.
 */
class TemplateSeeder extends Seeder
{
    public function __construct(
        private readonly TemplateService $templates,
    ) {}

    public function run(): void
    {
        // Повторный запуск не должен плодить копии
        if (Template::query()->exists()) {
            return;
        }

        $this->createTemplate(
            name: 'Счёт на оплату',
            description: 'Типовой счёт с реквизитами организации и таблицей услуг',
            builder: fn () => $this->buildInvoice(),
        );

        $this->createTemplate(
            name: 'Акт выполненных работ',
            description: 'Акт сдачи-приёмки оказанных услуг',
            builder: fn () => $this->buildAct(),
        );

        $this->createTemplate(
            name: 'Договор оказания услуг',
            description: 'Договор с заказчиком, реквизиты подставляются автоматически',
            builder: fn () => $this->buildContract(),
        );
    }

    /** Собирает docx во временном файле и загружает его как обычный шаблон. */
    private function createTemplate(string $name, string $description, callable $builder): void
    {
        $path = $builder();

        // Тот же путь, что и при загрузке через интерфейс: последний параметр
        // переводит UploadedFile в тестовый режим (файл не из HTTP-запроса)
        $file = new UploadedFile($path, "{$name}.docx", null, null, true);

        $this->templates->create([
            'name' => $name,
            'description' => $description,
            'organization_id' => null, // общий шаблон: доступен всем организациям
        ], $file);

        @unlink($path);
    }

    /** Счёт на оплату. */
    private function buildInvoice(): string
    {
        $word = new PhpWord;
        $this->setupStyles($word);

        $section = $word->addSection(['marginLeft' => 1100, 'marginRight' => 850, 'marginTop' => 850]);

        $section->addText('${org.full_name}', ['bold' => true, 'size' => 12]);
        $section->addText('ИНН ${org.inn} · КПП ${org.kpp} · ОГРН ${org.ogrn}', ['size' => 9]);
        $section->addText('${org.legal_address}', ['size' => 9]);
        $section->addText('Тел.: ${org.phone} · ${org.email}', ['size' => 9]);
        $section->addTextBreak(1);

        // Банковские реквизиты
        $bank = $section->addTable('requisites');
        $bank->addRow();
        $bank->addCell(4000)->addText('Банк получателя', ['size' => 9]);
        $bank->addCell(5500)->addText('${org.bank_name}', ['size' => 9, 'bold' => true]);
        $bank->addRow();
        $bank->addCell(4000)->addText('БИК', ['size' => 9]);
        $bank->addCell(5500)->addText('${org.bank_bik}', ['size' => 9]);
        $bank->addRow();
        $bank->addCell(4000)->addText('Расчётный счёт', ['size' => 9]);
        $bank->addCell(5500)->addText('${org.bank_account}', ['size' => 9]);
        $bank->addRow();
        $bank->addCell(4000)->addText('Корреспондентский счёт', ['size' => 9]);
        $bank->addCell(5500)->addText('${org.bank_corr_account}', ['size' => 9]);

        $section->addTextBreak(1);
        $section->addText(
            'Счёт на оплату № ${doc.number} от ${doc.date}',
            ['bold' => true, 'size' => 16],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 240]
        );

        $section->addText('Поставщик: ${org.full_name}', ['size' => 10]);
        $section->addText('Покупатель: ${client_name}, ИНН ${client_inn}, ${client_address}', ['size' => 10]);
        $section->addTextBreak(1);

        // Таблица позиций
        $items = $section->addTable('items');
        $items->addRow();
        $header = ['№' => 700, 'Наименование работ, услуг' => 4800, 'Кол-во' => 1200, 'Цена' => 1400, 'Сумма' => 1600];
        foreach ($header as $title => $width) {
            $items->addCell($width, ['bgColor' => 'F2F2F2'])
                ->addText($title, ['bold' => true, 'size' => 9], ['alignment' => Jc::CENTER]);
        }

        $items->addRow();
        $items->addCell(700)->addText('1', ['size' => 10], ['alignment' => Jc::CENTER]);
        $items->addCell(4800)->addText('${service_name}', ['size' => 10]);
        $items->addCell(1200)->addText('${service_count}', ['size' => 10], ['alignment' => Jc::CENTER]);
        $items->addCell(1400)->addText('${service_price}', ['size' => 10], ['alignment' => Jc::RIGHT]);
        $items->addCell(1600)->addText('${total_amount}', ['size' => 10], ['alignment' => Jc::RIGHT]);

        $section->addTextBreak(1);
        $section->addText('Итого к оплате: ${total_amount} руб.', ['bold' => true, 'size' => 12], ['alignment' => Jc::RIGHT]);
        $section->addText('Сумма прописью: ${total_in_words}', ['italic' => true, 'size' => 10], ['alignment' => Jc::RIGHT]);

        $section->addTextBreak(3);
        $section->addText('${org.director_position} _______________ / ${org.director_name} /', ['size' => 10]);

        return $this->save($word);
    }

    /** Акт выполненных работ. */
    private function buildAct(): string
    {
        $word = new PhpWord;
        $this->setupStyles($word);

        $section = $word->addSection(['marginLeft' => 1100, 'marginRight' => 850, 'marginTop' => 850]);

        $section->addText(
            'Акт № ${doc.number} от ${doc.date}',
            ['bold' => true, 'size' => 16],
            ['alignment' => Jc::CENTER]
        );
        $section->addText(
            'сдачи-приёмки оказанных услуг',
            ['size' => 11],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 360]
        );

        $section->addText('Исполнитель: ${org.full_name}, ИНН ${org.inn}', ['size' => 10]);
        $section->addText('Заказчик: ${client_name}, ИНН ${client_inn}', ['size' => 10]);
        $section->addTextBreak(1);

        $section->addText(
            'Исполнитель оказал, а Заказчик принял следующие услуги по договору '
            .'№ ${contract_number} от ${contract_date}:',
            ['size' => 10]
        );
        $section->addTextBreak(1);

        $items = $section->addTable('items');
        $items->addRow();
        foreach (['№' => 700, 'Наименование услуги' => 5600, 'Кол-во' => 1200, 'Сумма' => 2200] as $title => $width) {
            $items->addCell($width, ['bgColor' => 'F2F2F2'])
                ->addText($title, ['bold' => true, 'size' => 9], ['alignment' => Jc::CENTER]);
        }
        $items->addRow();
        $items->addCell(700)->addText('1', ['size' => 10], ['alignment' => Jc::CENTER]);
        $items->addCell(5600)->addText('${service_name}', ['size' => 10]);
        $items->addCell(1200)->addText('${service_count}', ['size' => 10], ['alignment' => Jc::CENTER]);
        $items->addCell(2200)->addText('${total_amount}', ['size' => 10], ['alignment' => Jc::RIGHT]);

        $section->addTextBreak(1);
        $section->addText('Всего оказано услуг на сумму ${total_amount} руб.', ['bold' => true, 'size' => 11]);
        $section->addText(
            'Услуги оказаны полностью и в срок. Заказчик претензий по объёму, качеству '
            .'и срокам оказания услуг не имеет.',
            ['size' => 10]
        );

        $section->addTextBreak(2);

        $signatures = $section->addTable('signatures');
        $signatures->addRow();
        $signatures->addCell(4800)->addText('Исполнитель', ['bold' => true, 'size' => 10]);
        $signatures->addCell(4800)->addText('Заказчик', ['bold' => true, 'size' => 10]);
        $signatures->addRow();
        $signatures->addCell(4800)->addText('${org.director_position}', ['size' => 10]);
        $signatures->addCell(4800)->addText('${client_director_position}', ['size' => 10]);
        $signatures->addRow();
        $signatures->addCell(4800)->addText('_________ / ${org.director_name} /', ['size' => 10]);
        $signatures->addCell(4800)->addText('_________ / ${client_director_name} /', ['size' => 10]);

        return $this->save($word);
    }

    /** Договор оказания услуг. */
    private function buildContract(): string
    {
        $word = new PhpWord;
        $this->setupStyles($word);

        $section = $word->addSection(['marginLeft' => 1100, 'marginRight' => 850, 'marginTop' => 850]);

        $section->addText(
            'Договор оказания услуг № ${doc.number}',
            ['bold' => true, 'size' => 15],
            ['alignment' => Jc::CENTER]
        );
        $section->addText('г. ${city}', ['size' => 10], ['alignment' => Jc::LEFT]);
        $section->addText('${doc.date}', ['size' => 10], ['alignment' => Jc::RIGHT, 'spaceAfter' => 240]);

        $section->addText(
            '${org.full_name}, именуемое в дальнейшем «Исполнитель», в лице '
            .'${org.director_position} ${org.director_name}, действующего на основании Устава, с одной стороны, '
            .'и ${client_name}, именуемое в дальнейшем «Заказчик», с другой стороны, '
            .'заключили настоящий договор о нижеследующем:',
            ['size' => 10],
            ['spaceAfter' => 240]
        );

        $section->addText('1. Предмет договора', ['bold' => true, 'size' => 11]);
        $section->addText('1.1. Исполнитель обязуется оказать услуги: ${contract_subject}.', ['size' => 10]);
        $section->addText('1.2. Срок оказания услуг: до ${contract_deadline}.', ['size' => 10], ['spaceAfter' => 240]);

        $section->addText('2. Стоимость и порядок расчётов', ['bold' => true, 'size' => 11]);
        $section->addText('2.1. Стоимость услуг составляет ${contract_amount} руб.', ['size' => 10]);
        $section->addText('2.2. Оплата производится в течение ${payment_days} дней с даты подписания акта.', ['size' => 10], ['spaceAfter' => 240]);

        $section->addText('3. Реквизиты сторон', ['bold' => true, 'size' => 11]);

        $requisites = $section->addTable('signatures');
        $requisites->addRow();
        $requisites->addCell(4800)->addText('Исполнитель', ['bold' => true, 'size' => 10]);
        $requisites->addCell(4800)->addText('Заказчик', ['bold' => true, 'size' => 10]);
        $requisites->addRow();
        $executor = $requisites->addCell(4800);
        $executor->addText('${org.full_name}', ['size' => 9]);
        $executor->addText('ИНН ${org.inn} / КПП ${org.kpp}', ['size' => 9]);
        $executor->addText('${org.legal_address}', ['size' => 9]);
        $executor->addText('р/с ${org.bank_account}', ['size' => 9]);
        $executor->addText('${org.bank_name}, БИК ${org.bank_bik}', ['size' => 9]);

        $customer = $requisites->addCell(4800);
        $customer->addText('${client_name}', ['size' => 9]);
        $customer->addText('ИНН ${client_inn}', ['size' => 9]);
        $customer->addText('${client_address}', ['size' => 9]);

        $section->addTextBreak(2);
        $section->addText('_________ / ${org.director_name} /          _________ / ${client_director_name} /', ['size' => 10]);

        return $this->save($word);
    }

    /** Общие стили документов: шрифт и оформление таблиц. */
    private function setupStyles(PhpWord $word): void
    {
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(11);

        $word->addTableStyle('items', [
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 60,
        ]);

        $word->addTableStyle('requisites', [
            'borderSize' => 6,
            'borderColor' => 'CCCCCC',
            'cellMargin' => 60,
        ]);

        // Таблица подписей без рамок — она нужна только для колонок
        $word->addTableStyle('signatures', ['cellMargin' => 60]);
    }

    /** Сохраняет собранный документ во временный файл. */
    private function save(PhpWord $word): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl_').'.docx';

        $word->save($path, 'Word2007');

        return $path;
    }
}
