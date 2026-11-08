<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MFS Assets v1 (card #426). Own tables only, like MFS Organisations: nothing is added to
// FreeScout's tables. Organisations, departments and locations come from MFS Organisations.
class CreateMfsassetsTables extends Migration
{
    public function up()
    {
        // Asset types (Laptop, Phone, Licence, ...) with their own fields, as JSON:
        // [{"key": "ram", "label": "RAM", "type": "text|number|date|select|checkbox", "options": ["8 GB", "16 GB"]}]
        Schema::create('mfsassets_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191)->unique();
            $table->text('fields')->nullable();
            $table->timestamps();
        });

        Schema::create('mfsassets_assets', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tag', 40)->unique();
            $table->string('name', 191);
            $table->integer('type_id')->unsigned()->nullable()->index();
            $table->string('status', 20)->default('in_use')->index();
            // Whose is it: an organisation (ourselves or a client).
            $table->integer('owner_id')->unsigned()->index();
            // Who uses it: an address, a department, or nobody (shared, on purpose).
            $table->string('assigned_email', 191)->nullable()->index();
            $table->integer('assigned_department_id')->unsigned()->nullable()->index();
            // Where is it: a location of an organisation, or none (digital).
            $table->integer('location_id')->unsigned()->nullable()->index();
            $table->string('serial', 191)->nullable()->index();
            $table->string('supplier', 191)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->date('warranty_end')->nullable()->index();
            $table->text('notes')->nullable();
            // Values of the type's own fields, as JSON {"ram": "16 GB"}.
            $table->text('field_values')->nullable();
            // Unguessable token for the QR label (report form / agent scan).
            $table->string('qr_token', 40)->unique();
            $table->integer('created_by')->unsigned()->nullable();
            $table->timestamps();
        });

        Schema::create('mfsassets_conversations', function (Blueprint $table) {
            $table->integer('asset_id')->unsigned();
            $table->integer('conversation_id')->unsigned()->index();
            $table->integer('linked_by')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->primary(['asset_id', 'conversation_id']);
        });

        // Audit trail: who changed what, when (changes of hands, moves, status).
        Schema::create('mfsassets_history', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('asset_id')->unsigned()->index();
            $table->integer('user_id')->unsigned()->nullable();
            $table->string('action', 30);
            $table->text('changes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('mfsassets_attachments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('asset_id')->unsigned()->index();
            $table->string('file_name', 191);
            $table->string('mime_type', 127)->nullable();
            $table->integer('size')->unsigned()->default(0);
            $table->string('path', 191);
            $table->integer('user_id')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('mfsassets_attachments');
        Schema::dropIfExists('mfsassets_history');
        Schema::dropIfExists('mfsassets_conversations');
        Schema::dropIfExists('mfsassets_assets');
        Schema::dropIfExists('mfsassets_types');
    }
}
