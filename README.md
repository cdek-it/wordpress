=== CDEKDelivery ===
Contributors: cdekit, caelan
Tags: ecommerce, shipping, delivery, woocommerce
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 6.5
Stable tag: dev
License: GPLv3

Integration with CDEK delivery for your WooCommerce store.

== Description ==

CDEKDelivery provides integration with CDEK delivery for your store on the WordPress WooCommerce platform. This 
plugin allows you to customize delivery settings according to your store requirements and allow customers to choose 
CDEK shipping when placing orders.

Main plugin features:

* Test mode for checking operation without real data integration.
* Processing of international orders and providing appropriate delivery options.
* Automatically sending orders to CDEK after checkout on the website.
* Selection of various rates for shipment based on customer requirements and product characteristics.
* You can change standard rate names to adapt them to specific needs.
* Multi-seater mode to distribute order items across different packages.
* Creation of a request for courier pickup.
* Parcel actual status on the admin order page.
* Extra days to the estimated delivery days, considering possible delays.
* Default product dimensions for more accurate shipping cost calculation.
* Printing of order receipts and barcodes for shipping.
* Provide a choice of various additional services, such as insurance and fitting, as well as flexible modification of the shipping cost depending on the selected services and order parameters.

## Особенности плагина
* Расчет стоимости и времени доставки
* Выбор пункта выдачи товаров через карту
* Простота установки, интеграция в WooCommerce
* Настройка данных магазина: адрес, выбор тарифа и типа отправки
* Возможность передачи актуальных данных об упаковке и автоматический подсчет веса заказа

## Доступы к сторонним сервисам

Плагин CDEKDelivery использует следующие сторонние сервисы для обеспечения своей функциональности:

1. **api.cdek.ru**: Для расчета стоимости и времени доставки заказа используется API CDEK. Политика конфиденциальности этого сервиса доступна по [ссылке](https://www.cdek.ru/ru/privacy_policy/)

2. **api.edu.cdek.ru**: Для расчета стоимости и времени доставки заказа в тестовом режиме используется API CDEK.
Политика конфиденциальности этого сервиса доступна по [ссылке](https://www.cdek.ru/ru/privacy_policy/)

== Installation ==

1. Установите плагин через меню "Плагины" в WordPress или загрузите архив в панели администратора.
2. Активируйте плагин.
3. Перейдите в раздел "WooCommerce" -> "Настройки" -> "Доставка" и выберите "CDEKDelivery".
4. Введите данные для подключения к API CDEK и настройте параметры доставки.
5. Заполните прочие настройки плагина и сохраните изменения.

Более подробная инструкция доступна по [ссылке](https://cdek-it.github.io/wordpress/)

== Frequently Asked Questions ==

= Куда можно задать вопрос по использованию плагина =

Все вопросы и замечания по использованию плагина можно задать на integrator@cdek.ru

== Сhangelog ==

Историю версий плагина после 3.18 вы можете найти в [Github](https://github.com/cdek-it/wordpress/releases)

= 3.18.0 =
* CMS-830 Добавлено обрезание крайних пробелов в строках адреса

= 3.17.0 =
* CMS-842 Переделано определение локации виджета на чекауте для подгрузки в него значений, использованных для калькулятора
* CMS-839 Исправлен расчет доставки на бою
* CMS-833 Исправлена ошибка при передаче веса по умолчанию
* CMS-851 Исправлена ошибка Uncaught TypeError: Cannot read properties of undefined (reading 'method_id')

= 3.16.1 =
* CMS-819 Исправлена ошибка создания накладной с вариативными товарами
* CMS-818 Исправлена ошибка при изменении метода доставки у заказа
* CMS-786 Увеличен интервал времени до автосоздания накладной, чтобы успела завершиться оплата и прогрузится товары
(5-10 минут)

= 3.16.0 =
* CMS-475 Добавлен чекбокс вкл/выкл автозакрытие карты ПВЗ
* CMS-784 В виджете показываются пвз, которые использовались для расчета калькулятора
* CMS-750 Исправлена ошибка при расчете комплекта товаров
* CMS-786 Хук автосоздания заказов перенесен на событие оформления заказа из вп

= 3.15.0 =
* CMS-317 Добавлена услуга "Запрет на осмотр"
* CMS-379 Добавлен вывод статусов в виджет на детальной заказа
* CMS-539 Добавлена услуга "Примерка" и "Частичная доставка"
* CMS-644 Исправлено позиционирование виджета

= 3.14.0 =
* CMS-408 Добавлена возможность настройть цену доставки в зависимости от режима
* CMS-554 Поле billing_postcode сделано необязательным для чекаута
* CMS-568 Поле billing_last_name сделано необязательным для чекаута
* CMS-576 Исправлена ошибка при пересчете стоимости доставки

= 3.13.0 =
* CMS-381 Добавлена совместимость с новым виджетом оформления заказа
* CMS-552 Исправлена ошибка Uncaught TypeError: Cdek\CdekApi::getCityCodeByCityName()
* CMS-553 Исправлена ошибка с дубликатами значений в логике подсчета габаритов коробки
* CMS-561 Исправлен конфликт с плагином управления полей Saphali

= 3.12.0 =
* CMS-262 При выборе доставки до пвз - адрес пвз не перезаписывает адрес клиента
* CMS-547 Исправлена ошибка с конвертацией веса в граммы при создании заказа

= 3.11.0 =
* CMS-343 Добавлена передача полей телефона, названия и электронной почты компании из настроек при создании накладной в
СДЭК
* CMS-370 Добавлена автоматическое создание накладных в СДЭК
* CMS-401 Добавлена возможность указания веса меньше 1кг в настройках
* CMS-499 Добавлен функционал кэширования авторизационного токена СДЭК для сокращения времени обработки запросов
* CMS-435 Добавлен IP исходящего соединения к сообщениям об ошибке
* CMS-494 При создании заказа передается его номер в вордпрессе
* CMS-488 Исправлен сбой при расчете стоимости доставки в чекауте

= 3.10.0 =
* CMS-400 Названия тарифы переименованы в соответствии с документацией к apiv2
* CMS-331 Доработан метод вычисления габаритов отправления с более точным алгоритмом
* CMS-292 Добавлена возможность пересчитать стоимость доставки в уже созданном заказе (beta версия, без контроля заказа
с измененными параметрами в СДЭК)
* CMS-260 В форму создания заказа в СДЭК подставляются значения, которые использовались калькулятором для вычислений
* CMS-486 Исправлен чекаут с требованием к наличию в заказе пвз при отправке тарифом ОТ офиса, а не ДО
* CMS-487 Возвращена возможность задать кастомное имя для тарифа

= 3.9.0 =
* CMS-253 Добавлена возможность использовать тариф "Сборный груз"
* CMS-476 Виджет-карта больше не подгружается на каждую страницу в админке
* CMS-472 Исправлен баг с открытием карты на старом городе при его изменении в чекауте
* CMS-482 Исправлен расчет доставки при отправке из-за границы в РФ

= 3.8.0 =
* CMS-405 Убрано ограничение на число символов ПД
* CMS-464 Создание заказа при не заполненных полях адреса отправителя теперь недоступно
* CMS-470 Исправлена ошибка с возможностью выбора нескольких пвз

= 3.7.0 =
* CMS-438 Отключена карта OSM и заменена картой с виджета
* CMS-450 Переписан чекаут на использование карты из виджета для совместимости с темами, создающими много кнопок выбора
карты
* CMS-453 Исправлена ошибка с созданием многоместки

= 3.6.0 =
* CMS-394 Постаматы при оформлении заказа теперь показываются отдельно цветом и названием на карте
* CMS-390 Поддержана функциональность Высокопроизводительного хранилища заказов Woocommerce
* CMS-248 Добавлена возможность настраивать параметры доставки СДЭК для каждой отдельной зоны доставки
* CMS-226 Переработана карта при выборе заказа. Теперь можно выбирать пункты из списка, расширена информация о каждом
пункте.
* CMS-208 Добавлена возможность печатать ШК
* CMS-395 Исправил конфликт с плагином avyyash-addons, из-за которого скрывались точки пвз на карте
* CMS-385 Плагин больше не перезаписывает обязательные поля у чекаута, если они уже существуют на странице

== Upgrade Notice ==

= 3.7.0 =
Карта заменена собственной разработкой от СДЭК и больше не содержит ошибочный данных из OSM

== Screenshots ==

1. Страница настроек CDEKDelivery.
2. Страница оформления заказа с выбором способа доставки CDEK.
