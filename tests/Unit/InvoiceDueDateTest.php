<?php

namespace Tests\Unit;

use App\Models\Invoice;
use Tests\TestCase;

class InvoiceDueDateTest extends TestCase
{
    public function test_due_date_is_two_days_after_the_invoice_date(): void
    {
        $dueDate = Invoice::dueDateFor('2026-10-01');

        $this->assertSame(2, Invoice::DUE_AFTER_DAYS);
        $this->assertSame('2026-10-03', $dueDate->toDateString());
    }
}
