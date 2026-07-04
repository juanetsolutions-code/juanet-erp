<?php

namespace App\Domain\CRM\Activities\Enums;

enum ActivityType: string
{
    case PHONE_CALL = 'phone_call';
    case MEETING = 'meeting';
    case EMAIL = 'email';
    case SMS = 'sms';
    case WHATSAPP = 'whatsapp';
    case INTERNAL_NOTE = 'internal_note';
    case FOLLOW_UP_TASK = 'follow_up_task';
    case APPOINTMENT = 'appointment';
    case DEMO = 'demo';
    case PROPOSAL = 'proposal';
    case QUOTE = 'quote';
    case FILE_ATTACHMENT = 'file_attachment';
    case STATUS_CHANGE = 'status_change';
    case ASSIGNMENT_CHANGE = 'assignment_change';
    case REMINDER = 'reminder';
    case SYSTEM_EVENT = 'system_event';
}
