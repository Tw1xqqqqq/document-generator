<?php

namespace App\Services\Templates;

use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

/**
 * Достаёт из docx-файла список меток вида ${client_name}.
 *
 * Почему не просто регулярное выражение по тексту: docx внутри — это zip
 * с xml, и Word нередко разрывает метку на части (${cli|ent_name}),
 * если в ней менялось форматирование. TemplateProcessor из PhpWord
 * умеет такие разрывы склеивать, поэтому берём список меток у него.
 */
class PlaceholderExtractor
{
    /** Префиксы, значения для которых подставляет сам сервис. */
    public const SYSTEM_PREFIXES = ['org', 'doc'];

    /**
     * Все метки файла, без дублей.
     *
     * @return list<string>
     */
    public function extract(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("Файл шаблона не найден: {$absolutePath}");
        }

        try {
            $processor = new TemplateProcessor($absolutePath);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Не удалось прочитать файл шаблона. Убедитесь, что это docx, а не doc или pdf.',
                previous: $e
            );
        }

        // getVariables() возвращает метки из основного текста,
        // колонтитулов и сносок — то, что нам и нужно.
        $variables = $processor->getVariables();

        return array_values(array_unique($variables));
    }

    /**
     * Делит метки на две группы: системные (подставляются сервисом)
     * и пользовательские (становятся полями формы).
     *
     * @param  list<string>  $placeholders
     * @return array{system: list<string>, user: list<string>}
     */
    public function split(array $placeholders): array
    {
        $system = [];
        $user = [];

        foreach ($placeholders as $placeholder) {
            if ($this->isSystem($placeholder)) {
                $system[] = $placeholder;
            } else {
                $user[] = $placeholder;
            }
        }

        return ['system' => $system, 'user' => $user];
    }

    /** Метка вида org.inn или doc.date заполняется автоматически. */
    public function isSystem(string $placeholder): bool
    {
        foreach (self::SYSTEM_PREFIXES as $prefix) {
            if (str_starts_with($placeholder, $prefix.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Подписи для часто встречающихся меток.
     * Покрывают типовые печатные формы: счёт, акт, договор.
     */
    private const KNOWN_LABELS = [
        'client_name' => 'Наименование клиента',
        'client_full_name' => 'Полное наименование клиента',
        'client_inn' => 'ИНН клиента',
        'client_kpp' => 'КПП клиента',
        'client_address' => 'Адрес клиента',
        'client_phone' => 'Телефон клиента',
        'client_email' => 'Email клиента',
        'client_director_name' => 'ФИО подписанта клиента',
        'client_director_position' => 'Должность подписанта клиента',
        'service_name' => 'Наименование услуги',
        'service_count' => 'Количество',
        'service_price' => 'Цена за единицу',
        'total_amount' => 'Сумма итого',
        'total_in_words' => 'Сумма прописью',
        'contract_number' => 'Номер договора',
        'contract_date' => 'Дата договора',
        'contract_subject' => 'Предмет договора',
        'contract_amount' => 'Сумма договора',
        'contract_deadline' => 'Срок оказания услуг',
        'payment_days' => 'Срок оплаты, дней',
        'city' => 'Город',
    ];

    /**
     * Человекочитаемая подпись для метки.
     *
     * Сначала смотрим словарь типовых полей, иначе собираем подпись
     * из самого ключа: client_name -> «Client name». Пользователь в любом
     * случае может поправить подпись в карточке шаблона.
     */
    public function humanize(string $placeholder): string
    {
        if (isset(self::KNOWN_LABELS[$placeholder])) {
            return self::KNOWN_LABELS[$placeholder];
        }

        $label = str_replace(['_', '.'], ' ', $placeholder);

        return mb_ucfirst(trim($label));
    }
}
