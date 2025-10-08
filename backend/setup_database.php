<?php
// backend/setup_database.php

// Ensure we can find bootstrap.php, assuming this script might be run from the project root.
$bootstrap_path = __DIR__ . '/bootstrap.php';
if (!file_exists($bootstrap_path)) {
    $bootstrap_path = __DIR__ . '/backend/bootstrap.php';
}
require_once $bootstrap_path;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Setup the database schema and seed initial data.
 */
function setup_database()
{
    // --- Schema for 'users' table ---
    if (!Capsule::schema()->hasTable('users')) {
        Capsule::schema()->create('users', function ($table) {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('email')->unique()->nullable();
            $table->timestamps();
        });
        echo "Table 'users' created successfully.\n";
    }

    // --- Schema for 'ai_prompts' table ---
    if (!Capsule::schema()->hasTable('ai_prompts')) {
        Capsule::schema()->create('ai_prompts', function ($table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->string('model'); // e.g., 'deepseek-chat'
            $table->text('prompt');
            $table->timestamps();
        });
        echo "Table 'ai_prompts' created successfully.\n";
    } else {
        if (!Capsule::schema()->hasColumn('ai_prompts', 'model')) {
            Capsule::schema()->table('ai_prompts', function ($table) {
                $table->string('model')->default('deepseek-chat')->after('name');
            });
            echo "Column 'model' added to 'ai_prompts' table.\n";
        }
    }


    // --- Schema for 'bills' table ---
    if (!Capsule::schema()->hasTable('bills')) {
        Capsule::schema()->create('bills', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('bill_id')->unique();
            $table->string('sender');
            $table->decimal('total_amount', 10, 2);
            $table->json('details'); // Store items as JSON
            $table->text('body_html')->nullable(); // For storing the raw HTML of the email
            $table->timestamp('received_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
        echo "Table 'bills' created successfully.\n";
    } else {
        // Add body_html column if it doesn't exist for backward compatibility
        if (!Capsule::schema()->hasColumn('bills', 'body_html')) {
            Capsule::schema()->table('bills', function ($table) {
                $table->text('body_html')->nullable()->after('details');
            });
            echo "Column 'body_html' added to 'bills' table.\n";
        }
    }

    // --- Schema for 'user_states' table ---
    if (!Capsule::schema()->hasTable('user_states')) {
        Capsule::schema()->create('user_states', function ($table) {
            $table->bigInteger('user_id')->unsigned()->primary(); // Telegram user IDs can be large
            $table->string('state'); // e.g., 'waiting_for_email_address'
            $table->json('state_data')->nullable(); // For storing temporary data related to the state
            $table->timestamp('updated_at')->useCurrent();
        });
        echo "Table 'user_states' created successfully.\n";
    }

    // --- Schema for 'allowed_emails' table ---
    if (!Capsule::schema()->hasTable('allowed_emails')) {
        Capsule::schema()->create('allowed_emails', function ($table) {
            $table->increments('id');
            $table->string('email')->unique();
            $table->timestamp('created_at')->useCurrent();
        });
        echo "Table 'allowed_emails' created successfully.\n";
    }

    // --- Schema for 'api_keys' table ---
    if (!Capsule::schema()->hasTable('api_keys')) {
        Capsule::schema()->create('api_keys', function ($table) {
            $table->increments('id');
            $table->string('key_name')->unique();
            $table->text('key_value');
            $table->timestamp('updated_at')->useCurrent();
        });
        echo "Table 'api_keys' created successfully.\n";
    }

    // --- Seed AI Prompts ---
    seed_ai_prompts();

    echo "Database setup completed!\n";
}

/**
 * Seed the ai_prompts table with the default DeepSeek prompt.
 */
function seed_ai_prompts()
{
    $deepseek_prompt = <<<'EOT'
你是一个专门解析投注单据的AI。严格按照指定的JSON格式从文本中提取信息。不要添加任何说明或评论。如果某个字段找不到对应信息，则其值应为null。

```json
{
  "bill_id": "string | null",
  "sender": "string | null",
  "total_amount": "number | null",
  "details": [
    {
      "item": "string",
      "amount": "number",
      "result": "string<win/loss/draw>"
    }
  ]
}
```
EOT;

    // Upsert DeepSeek Prompt as the one and only default
    Capsule::table('ai_prompts')->updateOrInsert(
        ['name' => 'betting_slip_parser'],
        [
            'model' => 'deepseek-chat',
            'prompt' => $deepseek_prompt,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]
    );
    echo "Seeded/Updated 'betting_slip_parser' prompt for DeepSeek.\n";
    
    // Clean up old Gemini-specific prompt if it exists
    Capsule::table('ai_prompts')->where('name', 'betting_slip_parser_deepseek')->delete();
    Capsule::table('ai_prompts')->where('model', 'gemini-pro')->delete();
    echo "Cleaned up any old model-specific prompts.\n";
}

// Run the setup if the script is called directly
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    setup_database();
}
