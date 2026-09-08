    <?php

    use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_hub_id')->nullable(); // Matches your form hidden input

            // General / Common & Shopee/Lazada requested fields
            $table->string('channel_type');
            $table->date('order_date'); // Placed Order Date
            $table->date('date_of_arrangement')->nullable(); // Date of Arrangement
            $table->string('customer_name'); // Buyer Name
            $table->string('order_number'); // Invoice No / Order Number
            $table->string('location')->nullable(); // Location
            $table->string('mode_of_payment')->nullable(); // MOP
            $table->string('proof_of_payment')->nullable();

            // TikTok Specific fields
            $table->decimal('sales_after_fee', 10, 2)->nullable();
            $table->date('drop_off_date')->nullable();
            $table->string('courier')->nullable();
            $table->string('order_status')->nullable();
            $table->string('payment_status')->nullable();

            // Wholesale / Online Specific fields
            $table->string('contact_number')->nullable();
            $table->text('address')->nullable();
            $table->string('pack')->nullable();
            $table->string('pickup')->nullable();
            $table->string('note')->nullable();
            $table->string('packed')->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);

            // Walk-In Specific fields
            $table->string('pos')->nullable();
            $table->string('pic')->nullable();
            $table->text('remarks')->nullable();

            // Financial Totals
            $table->decimal('sub_total', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0); // Amount

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
