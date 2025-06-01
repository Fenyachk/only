<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
    die();

use Bitrix\Highloadblock as HL;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Loader;


class CarBookingApiComponent extends CBitrixComponent
{
    private int $userId;
    private array $allowedCategories;

    private const HL_CAR_ID = 4;
    private const HL_EMPLOYEE_ID = 5;
    private const HL_BOOKING_ID = 6;


    public function __construct($component = null)
    {
        parent::__construct($component);
    }

    public function executeComponent()
    {
        if (!\Bitrix\Main\Loader::includeModule('highloadblock')) {
            $this->respond(['error' => 'Не удалось подключить модуль highloadblock'], 500);
            return;
        }

        $this->userId = (int) ($this->arParams['EMPLOYEE_ID'] ?? 0);
        if (!$this->userId) {
            $this->respond(['error' => 'Не передан ID сотрудника'], 400);
            return;
        }

        $this->allowedCategories = $this->loadAllowedCategories();
        [$start, $end] = $this->prepareDateRange();

        $availableCars = $this->getAvailableCars($start, $end);
        if (empty($availableCars)) {
            $this->respond(['error' => 'Нет доступных машин'], 404);
            return;
        }

        $this->params = [
            'EMPLOYEE_ID' => $this->userId,
            'CAR_ID' => $availableCars[0]['ID'],
            'START' => $start,
            'END' => $end,
        ];

        $this->createBooking();
    }

    private function prepareDateRange(): array
    {
        $startRaw = str_replace('T', ' ', $this->arParams['START_DATE'] ?? '');
        $endRaw = str_replace('T', ' ', $this->arParams['END_DATE'] ?? '');

        $start = new \Bitrix\Main\Type\DateTime((new \DateTimeImmutable($startRaw))->format('d.m.Y H:i:s'));
        $end = new \Bitrix\Main\Type\DateTime((new \DateTimeImmutable($endRaw))->format('d.m.Y H:i:s'));

        return [$start, $end];
    }

    private function getDataClass(int $hlblockId)
    {
        $hlblock = HL\HighloadBlockTable::getById($hlblockId)->fetch();
        $entity = HL\HighloadBlockTable::compileEntity($hlblock);
        return $entity->getDataClass();
    }

    private function loadAllowedCategories(): array
    {
        $entityDataClass = $this->getDataClass(self::HL_EMPLOYEE_ID);

        $res = $entityDataClass::getList([
            'filter' => ['ID' => $this->userId],
            'select' => ['UF_ALLOWED_CATEGORIES']
        ])->fetch();

        return $res['UF_ALLOWED_CATEGORIES'] ?? [];
    }

    public function getAvailableCars(DateTime $start, DateTime $end): array
    {

        $carClass = $this->getDataClass(self::HL_CAR_ID);
        $bookingClass = $this->getDataClass(self::HL_BOOKING_ID);

        $booked = $bookingClass::getList([
            'filter' => [
                'LOGIC' => 'OR',
                [
                    '<UF_DATE_START' => $end,
                    '>UF_DATE_END' => $start
                ]
            ],
            'select' => ['UF_CAR_ID']
        ]);

        $bookedCarIds = [];
        while ($row = $booked->fetch()) {
            $bookedCarIds[] = $row['UF_CAR_ID'];
        }

        $carFilter = [
            'UF_CATEGORY' => $this->allowedCategories,
            'UF_DRIVER_ID' => $this->userId, 
        ];
        if (!empty($bookedCarIds)) {
            $carFilter['!ID'] = $bookedCarIds;
        }

        $cars = [];
        $res = $carClass::getList([
            'filter' => $carFilter,
            'select' => ['ID', 'UF_NAME', 'UF_CATEGORY', 'UF_DRIVER_ID']
        ]);

        while ($row = $res->fetch()) {
            $cars[] = $row;
        }

        return $cars;
    }


    protected function createBooking()
    {

        $hl = HL\HighloadBlockTable::getById(self::HL_BOOKING_ID)->fetch();
        $entity = HL\HighloadBlockTable::compileEntity($hl);
        $entityDataClass = $entity->getDataClass();

        $result = $entityDataClass::add([
            'UF_EMPLOYEE_ID' => $this->params['EMPLOYEE_ID'],
            'UF_CAR_ID' => $this->params['CAR_ID'],
            'UF_DATE_START' => $this->params['START'],
            'UF_DATE_END' => $this->params['END'],
        ]);

        if ($result->isSuccess()) {
            $this->respond(['success' => true]);
        } else {
            $this->respond(['error' => $result->getErrorMessages()], 500);
        }
    }

    protected function respond(array $data, int $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        \CMain::FinalActions();
        die();
    }

}

