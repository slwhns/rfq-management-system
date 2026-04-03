<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropQuoteForeignIfExists('quote_items');
        $this->dropQuoteForeignIfExists('quote_status_histories');

        $this->renameIfNeeded('quotes', 'purchase_requests');
        $this->renameIfNeeded('quote_items', 'purchase_request_items');
        $this->renameIfNeeded('quote_status_histories', 'purchase_request_status_histories');

        $this->addQuoteForeignIfPossible('purchase_request_items', 'purchase_requests');
        $this->addQuoteForeignIfPossible('purchase_request_status_histories', 'purchase_requests');
    }

    public function down(): void
    {
        $this->dropQuoteForeignIfExists('purchase_request_items');
        $this->dropQuoteForeignIfExists('purchase_request_status_histories');

        $this->renameIfNeeded('purchase_request_status_histories', 'quote_status_histories');
        $this->renameIfNeeded('purchase_request_items', 'quote_items');
        $this->renameIfNeeded('purchase_requests', 'quotes');

        $this->addQuoteForeignIfPossible('quote_items', 'quotes');
        $this->addQuoteForeignIfPossible('quote_status_histories', 'quotes');
    }

    private function renameIfNeeded(string $from, string $to): void
    {
        if (Schema::hasTable($from) && !Schema::hasTable($to)) {
            Schema::rename($from, $to);
        }
    }

    private function dropQuoteForeignIfExists(string $tableName): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'quote_id')) {
            return;
        }

        $constraintName = $this->getForeignKeyNameForQuoteId($tableName);
        if ($constraintName === null) {
            return;
        }

        DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraintName}`");
    }

    private function addQuoteForeignIfPossible(string $childTable, string $parentTable): void
    {
        if (!Schema::hasTable($childTable) || !Schema::hasTable($parentTable) || !Schema::hasColumn($childTable, 'quote_id')) {
            return;
        }

        if ($this->getForeignKeyNameForQuoteId($childTable) !== null) {
            return;
        }

        Schema::table($childTable, function (Blueprint $table) use ($parentTable) {
            $table->foreign('quote_id')->references('id')->on($parentTable)->onDelete('cascade');
        });
    }

    private function getForeignKeyNameForQuoteId(string $tableName): ?string
    {
        $databaseName = DB::getDatabaseName();

        $row = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->select('CONSTRAINT_NAME')
            ->where('TABLE_SCHEMA', $databaseName)
            ->where('TABLE_NAME', $tableName)
            ->where('COLUMN_NAME', 'quote_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->first();

        return $row?->CONSTRAINT_NAME;
    }
};
