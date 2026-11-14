<?php

use App\Services\Knowledge\PiiMasker;

it('masks emails, phone numbers and card numbers but keeps names and figures', function (string $input, string $expected) {
    expect((new PiiMasker)->mask($input))->toBe($expected);
})->with([
    ['Call Dana at 416-555-0142.', 'Call Dana at [phone].'],
    ['Reach her on (905) 555 0199 or +1 647.555.0100', 'Reach her on [phone] or [phone]'],
    ['Email amara.singh@example.com about the quote', 'Email [email] about the quote'],
    ['Paid with 4111 1111 1111 1111', 'Paid with [card number]'],
    ['Tows a 2,000 lb boat, budget $45,000', 'Tows a 2,000 lb boat, budget $45,000'],
]);
