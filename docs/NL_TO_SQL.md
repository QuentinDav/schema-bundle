# Natural Language to SQL (NL→SQL)

## Overview

The **QdSchemaBundle** now includes an advanced **Natural Language to SQL** feature that allows you to generate SQL queries from natural language prompts using:

- **Local rule-based engine** (frontend, no cost)
- **AI-powered generation** (OpenAI, Anthropic, Mistral, Grok, Ollama)
- **Hybrid approach** (local first, AI fallback if confidence is low)

---

## Architecture

```
User Input (Natural Language)
        ↓
[NlToSqlOrchestrator]
        ↓
    ┌─────────────────────┐
    │ Strategy Selection  │
    └─────────────────────┘
        ↓
    ┌────────┬──────────┬─────────┐
    │ Local  │   AI     │ Hybrid  │
    └────────┴──────────┴─────────┘
        ↓
    SQL Query + Metadata + Cost
```

---

## Installation

### 1. Install Symfony AI Bundle (Optional)

If you want to use AI-powered generation:

```bash
composer require symfony/ai-bundle
```

**Note:** Symfony AI is experimental and not covered by Symfony's Backward Compatibility Promise.

### 2. Configure Your AI Provider

Symfony AI supports multiple providers via its unified Platform interface. Choose one and configure it:

#### OpenAI (Recommended)

**Step 1:** Add your API key to `.env`:

```bash
# .env.local
OPENAI_API_KEY=sk-your-key-here
```

**Step 2:** Configure the AI Platform in your `services.yaml`:

```yaml
# config/services.yaml
services:
  # Create OpenAI Platform instance
  qd_schema.ai_platform:
    class: Symfony\AI\Platform\OpenAi\Client
    factory: ['Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory', 'create']
    arguments:
      - '%env(OPENAI_API_KEY)%'
      - '@http_client'
```

**Step 3:** Configure QdSchemaBundle:

```yaml
# config/packages/qd_schema.yaml
qd_schema:
  nl_to_sql:
    enabled: true
    strategy: hybrid  # 'local', 'ai', or 'hybrid'
    confidence_threshold: 0.7

    ai:
      model: gpt-4-turbo  # or gpt-3.5-turbo for cheaper option
      max_tokens: 1000
      temperature: 0.2

    cost:
      warn_threshold: 0.10
      max_per_request: 0.50
```

#### Anthropic Claude

**Step 1:** Add your API key:

```bash
# .env.local
ANTHROPIC_API_KEY=sk-ant-your-key-here
```

**Step 2:** Configure the Platform:

```yaml
# config/services.yaml
services:
  qd_schema.ai_platform:
    class: Symfony\AI\Platform\Anthropic\Client
    factory: ['Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory', 'create']
    arguments:
      - '%env(ANTHROPIC_API_KEY)%'
      - '@http_client'
```

**Step 3:** Configure model:

```yaml
# config/packages/qd_schema.yaml
qd_schema:
  nl_to_sql:
    ai:
      model: claude-3-sonnet  # or claude-3-opus, claude-3-haiku
```

#### Azure OpenAI

```yaml
# config/services.yaml
services:
  qd_schema.ai_platform:
    class: Symfony\AI\Platform\Azure\Client
    factory: ['Symfony\AI\Platform\Bridge\Azure\PlatformFactory', 'create']
    arguments:
      - '%env(AZURE_API_KEY)%'
      - '%env(AZURE_ENDPOINT)%'
      - '@http_client'
```

#### Ollama (Local, Free)

**Step 1:** Install and run Ollama locally:

```bash
# Install Ollama (https://ollama.ai)
ollama pull llama3
ollama serve
```

**Step 2:** Configure the Platform:

```yaml
# config/services.yaml
services:
  qd_schema.ai_platform:
    class: Symfony\AI\Platform\Ollama\Client
    factory: ['Symfony\AI\Platform\Bridge\Ollama\PlatformFactory', 'create']
    arguments:
      - 'http://localhost:11434'  # Ollama default URL
      - '@http_client'
```

**Step 3:** Configure model:

```yaml
# config/packages/qd_schema.yaml
qd_schema:
  nl_to_sql:
    ai:
      model: llama3  # or codellama, mistral, etc.
    cost:
      warn_threshold: 0  # Ollama is free
      max_per_request: 0
```

---

## Configuration Reference

```yaml
qd_schema:
  nl_to_sql:
    # Enable/disable the feature
    enabled: true

    # Strategy: 'local', 'ai', or 'hybrid'
    # - local: Use frontend rule-based engine only
    # - ai: Use AI generation only
    # - hybrid: Try local first, fallback to AI if confidence < threshold
    strategy: local

    # Confidence threshold for hybrid mode (0.0 - 1.0)
    confidence_threshold: 0.7

    ai:
      # AI provider: 'openai', 'anthropic', 'mistral', 'grok', 'ollama'
      provider: null

      # Model to use
      model: gpt-4-turbo

      # Maximum tokens for AI response
      max_tokens: 1000

      # Temperature (0.0 = deterministic, 2.0 = creative)
      temperature: 0.2

    cost:
      # Warn if estimated cost exceeds this (USD)
      warn_threshold: 0.10

      # Block if estimated cost exceeds this (USD)
      max_per_request: 0.50
```

---

## Usage

### Frontend (Vue 3)

The Query Builder panel automatically detects AI availability and shows a toggle:

```vue
<!-- Automatically integrated in QueryBuilderPanel.vue -->
<label>
  <input type="checkbox" v-model="useAi" />
  Enhance with AI (GPT-4 Turbo)
</label>
```

Users can:
- Enter a natural language query
- Toggle AI enhancement on/off
- See estimated cost before generation
- View actual cost after generation

### API Endpoints

#### Generate SQL

```bash
POST /api/nl-to-sql/generate
Content-Type: application/json

{
  "prompt": "Get all users with their addresses",
  "strategy": "ai"  // optional: 'local', 'ai', 'hybrid'
}
```

Response:

```json
{
  "success": true,
  "sql": "SELECT u.*, a.* FROM users u LEFT JOIN addresses a ON u.id = a.user_id",
  "confidence": 0.95,
  "explanation": "This query retrieves all users with their associated addresses.",
  "entities": [
    {"name": "User", "tableName": "users"},
    {"name": "Address", "tableName": "addresses"}
  ],
  "provider": "gpt-4-turbo",
  "cost": {
    "estimated": 0.0045,
    "actual": 0.0042,
    "currency": "USD",
    "input_tokens": 450,
    "output_tokens": 120,
    "total_tokens": 570
  }
}
```

#### Estimate Cost

```bash
POST /api/nl-to-sql/estimate-cost
Content-Type: application/json

{
  "prompt": "Get all users with their addresses",
  "strategy": "ai"
}
```

Response:

```json
{
  "success": true,
  "estimate": {
    "amount": 0.0045,
    "currency": "USD",
    "model": "gpt-4-turbo",
    "estimated_input_tokens": 450,
    "estimated_output_tokens": 500
  }
}
```

#### Check Status

```bash
GET /api/nl-to-sql/status
```

Response:

```json
{
  "enabled": true,
  "ai_available": true
}
```

---

### CLI Command

Test the generator from the command line:

```bash
# Basic usage (uses configured strategy)
php bin/console qd:nl-to-sql:test "Get all users with their addresses"

# Specify strategy
php bin/console qd:nl-to-sql:test "Find training in Paris" --strategy=ai

# Compare all strategies
php bin/console qd:nl-to-sql:test "List users where age > 18" --compare

# Show cost breakdown
php bin/console qd:nl-to-sql:test "Get training data" --show-cost

# Show available entities
php bin/console qd:nl-to-sql:test "Your query" --show-entities
```

---

## Pricing (as of 2025)

| Provider | Model | Input (per 1K tokens) | Output (per 1K tokens) |
|----------|-------|----------------------|------------------------|
| OpenAI | GPT-4 Turbo | $0.01 | $0.03 |
| OpenAI | GPT-3.5 Turbo | $0.0005 | $0.0015 |
| Anthropic | Claude 3 Opus | $0.015 | $0.075 |
| Anthropic | Claude 3 Sonnet | $0.003 | $0.015 |
| Anthropic | Claude 3 Haiku | $0.00025 | $0.00125 |
| Mistral | Mistral Large | $0.004 | $0.012 |
| Mistral | Mistral Small | $0.0010 | $0.0030 |
| Ollama | (local) | $0.00 | $0.00 |

**Typical query cost**: $0.002 - $0.01 per generation (depending on model and schema size)

---

## Strategies Explained

### Local Strategy

- **How it works**: Uses the frontend JavaScript NLP engine (rule-based)
- **Cost**: Free
- **Pros**: Fast, no API costs, privacy-friendly
- **Cons**: Limited to simple queries, may have lower confidence
- **Best for**: Simple queries, development, cost-sensitive applications

### AI Strategy

- **How it works**: Sends prompt + schema context to LLM
- **Cost**: $0.002 - $0.01 per query
- **Pros**: Handles complex queries, high confidence, natural language understanding
- **Cons**: API cost, requires internet, slower
- **Best for**: Complex queries, production with budget

### Hybrid Strategy (Recommended)

- **How it works**: Tries local first, falls back to AI if confidence < threshold
- **Cost**: Variable (only pays when AI is used)
- **Pros**: Best of both worlds, cost-optimized
- **Cons**: Slightly more complex logic
- **Best for**: Most applications

---

## Security Considerations

### API Key Protection

**Never commit API keys to your repository!**

Use environment variables:

```bash
# .env.local (NOT committed)
OPENAI_API_KEY=sk-your-key-here
ANTHROPIC_API_KEY=sk-ant-your-key-here
```

### Cost Limits

Always configure cost thresholds to prevent unexpected bills:

```yaml
qd_schema:
  nl_to_sql:
    cost:
      warn_threshold: 0.10   # Warn at $0.10
      max_per_request: 0.50  # Block at $0.50
```

### Rate Limiting

Consider adding rate limiting to prevent abuse:

```yaml
# config/packages/rate_limiter.yaml
framework:
  rate_limiter:
    nl_to_sql_generation:
      policy: 'sliding_window'
      limit: 10
      interval: '1 minute'
```

---

## Troubleshooting

### AI Not Available

**Problem**: Frontend shows no AI toggle, or API returns `ai_available: false`

**Solutions**:
1. Check that `symfony/ai` is installed: `composer show symfony/ai`
2. Verify provider configuration in `qd_schema.yaml`
3. Check API key is set in `.env`
4. Check logs: `tail -f var/log/dev.log`

### High Costs

**Problem**: Unexpected high API costs

**Solutions**:
1. Lower `max_per_request` threshold
2. Use cheaper model (e.g., `gpt-3.5-turbo` instead of `gpt-4`)
3. Use `hybrid` strategy to reduce AI calls
4. Consider Ollama (local, free)

### Low Confidence

**Problem**: Queries consistently have low confidence

**Solutions**:
1. Check schema is properly extracted: `php bin/console qd:nl-to-sql:test "query" --show-entities`
2. Use more explicit entity names in prompts
3. Lower `confidence_threshold` in hybrid mode
4. Switch to `ai` strategy for better results

---

## Examples

### Simple Query

```bash
Input: "Get all users"
Output: SELECT * FROM users
Confidence: 0.92
Provider: local
Cost: Free
```

### Join Query

```bash
Input: "List users with their addresses"
Output: SELECT u.*, a.* FROM users u LEFT JOIN addresses a ON u.id = a.user_id
Confidence: 0.88
Provider: gpt-4-turbo
Cost: $0.0035
```

### Complex Query

```bash
Input: "Find training sessions in Paris with more than 10 participants, ordered by date"
Output: SELECT t.* FROM training t
        JOIN addresses a ON t.address_id = a.id
        WHERE a.city = 'Paris' AND t.participants > 10
        ORDER BY t.date DESC
Confidence: 0.85
Provider: claude-3-sonnet
Cost: $0.0028
```

---

## Roadmap

- [ ] Query validation before execution
- [ ] Query history and caching
- [ ] Multi-language support
- [ ] Query optimization suggestions
- [ ] Integration with Doctrine QueryBuilder
- [ ] Fine-tuned local models

---

## Contributing

Found a bug or have a feature request? Open an issue on [GitHub](https://github.com/quentindavid/qd-schema-bundle/issues).

---

## License

MIT License - see [LICENSE](../LICENSE) for details.
