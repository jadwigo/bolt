<?php

namespace Bolt\Storage\Migration\Transformer;

use Bolt\Common\Exception\ParseException;
use Bolt\Common\Json;
use Bolt\Storage\Field\Type;
use Bolt\Storage\Migration\Field;

/**
 * Transformer for multi-select field data.
 *
 * @internal
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class SelectMultipleTypeTransformer implements TypeTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(Field $field)
    {
        return $field->getMeta()->getPath('fieldtype') === Type\SelectMultipleType::class;

    }

    /**
     * {@inheritdoc}
     */
    public function transform(Field $field)
    {
        if (!$this->supports($field)) {
            return;
        }
        if ($field->getValue() === null) {
            return;
        }
        if (empty($field->getValue())) {
            return;
        }
        try {
            Json::parse($field->getValue());
        } catch (ParseException $e) {
            $this->normalize($field);
        }

        return;
    }

    /**
     * Ensure an multi-select field data is correctly structured.
     *
     * @param Field $field
     */
    private function normalize(Field $field)
    {
        $field->setValue(Json::dump([$field->getValue()]));
    }
}
