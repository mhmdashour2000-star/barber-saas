<?php

namespace App\Enums;

enum ConversationState: string
{
    case START = 'start';
    case AWAITING_NAME = 'awaiting_name';
    case MAIN_MENU = 'main_menu';
    case SELECT_SERVICE = 'select_service';
    case SELECT_BARBER = 'select_barber';
    case SELECT_DAY = 'select_day';
    case SELECT_TIME = 'select_time';
    case CONFIRM_BOOKING = 'confirm_booking';
    case BOOKING_COMPLETE = 'booking_complete';
    case INQUIRY_MENU = 'inquiry_menu';
    case CANCEL_SELECT = 'cancel_select';
    case CANCEL_CONFIRM = 'cancel_confirm';
}
