<?php

namespace App\Enums;

enum MailTemplates: string
{
    case LISTING_REMINDER = '78eaccd6-5e0e-4de1-a1de-d40a462b7bc9';
    case SUBMITTED_LISTING = '6c273020-137e-4604-88eb-e1f9bef8e83d';
    case LISTING_APPROVED = 'b0eb626a-9d16-444c-8c33-7ec7dcfae02a';
    case LISTING_INFO_NEEDED = 'c836ff25-eeda-4188-8af4-6713f09acea8';
    case LISTING_REJECTED = '85569b3b-2a35-4577-98d2-7bca990cc1cf';
}
