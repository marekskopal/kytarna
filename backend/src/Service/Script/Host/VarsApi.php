<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Host;

use Ukolio\Service\Script\ScriptVariableProviderInterface;

/**
 * Exposed to JS as `ukolio.vars`: a workspace-scoped key/value store. Secret values are decrypted
 * on read and registered for log redaction; writes with { secret: true } are encrypted at rest.
 */
final readonly class VarsApi
{
	public function __construct(private ScriptRunContext $context, private ScriptVariableProviderInterface $variables,)
	{
	}

	public function get(string $key): ?string
	{
		$variable = $this->variables->get($this->context->workspace, $key);
		if ($variable === null) {
			return null;
		}

		$value = $this->variables->decrypt($variable);
		if ($variable->isSecret) {
			$this->context->registerSecret($value);
		}

		return $value;
	}

	public function set(string $key, string $value, mixed $options = null): void
	{
		$isSecret = (bool) (JsValue::toAssoc($options)['secret'] ?? false);

		$this->variables->set($this->context->workspace, $key, $value, $isSecret);

		if ($isSecret) {
			$this->context->registerSecret($value);
		}
	}
}
