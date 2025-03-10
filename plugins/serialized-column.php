<?php
class AdminerSerializedColumn
{
	private function _testSerialized ($value)
	{
		if (preg_match('^([adObis]:|N;)^', $value) && $data = unserialize($value))
			return $data;
		return $value;
	}

	private function _buildTable ($data)
	{
		echo '<table cellspacing="0" style="margin:2px" data-title="PHP Serialized">';
		if (is_array($data) && !empty($data))
		{
			foreach ($data as $key => $val)
			{
				echo '<tr>';
				echo '<th>' . h($key) . '</th>';
				echo '<td>';
				if (is_scalar($val) || $val === null)
				{
					if (is_bool($val))
					{
						$val = $val ? 'true' : 'false';
					}
					elseif ($val === null)
					{
						$val = 'null';
					}
					elseif (!is_numeric($val))
					{
						$val = '"' . h(addcslashes($val, "\r\n\"")) . '"';
					}
					echo '<code class="jush-js">' . $val . '</code>';
				}
				else
				{
					$this->_buildTable($val);
				}
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</table>';
	}

	function editInput ($table, $field, $attrs, $value)
	{
		$data = $this->_testSerialized($value);
		if ($data !== $value)
		{
			$this->_buildTable($data);
		}
	}
}
