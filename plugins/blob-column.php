<?php
class AdminerBlobColumn
{
	function editInput ($table, $field, $attrs, $value)
	{
		if ($field['type'] === 'blob' && strlen($value) > 0)
		{
			printf('<code style="display: block; margin-bottom: 5px; padding: 5px 10px; color: #666; max-width: 60vw; white-space: pre-wrap; word-break: break-all">%s</code>', htmlspecialchars(preg_replace_callback('/[^\x20-\x7f]/', function ($in) { return sprintf('<%02X>', ord($in[0])); }, $value)));
		}
	}
}
