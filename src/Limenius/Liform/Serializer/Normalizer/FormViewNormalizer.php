<?php

namespace Limenius\Liform\Serializer\Normalizer;

use Symfony\Component\Form\FormView;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Class: FormViewNormalizer
 *
 * @see NormalizerInterface
 */
class FormViewNormalizer implements NormalizerInterface
{

    /**
     * For some widgets, such as the ArrayField it is necessary to serialise the form as an array not an object.
     */
    const NORMALIZATION_STRATEGY = 'NormalizationStrategy';
    /**
     * Normalize the children of the form as an array, not an object.
     */
    const CHILDREN_AS_ARRAY = 'ChildrenAsArray';
    /**
     * Normalise the children by producing an array of the name of the child if the value is true.
     */
    const CHILDREN_AS_TRUE_NAMES = 'ChildrenAsTrueNames';

    /**
     * {@inheritdoc}
     */
    public function normalize($form, $format = null, array $context = [])
    {
        $useArray = $this->useArrayForChildren($form);

        if ( ! empty($form->children)) {
            $serializedForm = $useArray ? [] : (object) [];

            foreach ($form->children as $name => $child) {
                // Skip empty values because
                // https://github.com/erikras/redux-form/issues/2149
                if (empty($child->children) && ($child->vars['value'] === null || $child->vars['value'] === '')) {
                    continue;
                }

                $normalChild = $this->normalize($child);

                if ($useArray) {
                    $returnNameAsChild = $this->useNameAsChild($form);

                    if ($returnNameAsChild) {
                        if ($normalChild === true) {
                            $serializedForm[] = "$name";
                        }
                    } else {
                        $serializedForm[] = $normalChild;
                    }
                } else {
                    $serializedForm->{$name} = $normalChild;
                }
            }

            return $serializedForm;
        } else {
            // handle separately the case with checkboxes, so the result is
            // true/false instead of 1/0
            if (isset($form->vars['checked'])) {
                return $form->vars['checked'];
            }

            $value = $form->vars['value'];

            //If this is an iterable it's likely that the widget is expecting an array, not stdObject
            if ($value instanceof \Traversable && iterator_count($value) === 0 && $useArray) {
                return [];
            }

            return $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof FormView;
    }

    /**
     * In some cases the children of the form should be serialized as an array. This is controlled by a var on the
     * FormView.
     *
     * @param $form
     *
     * @return bool
     *
     * @see FormViewNormalizer::NORMALIZATION_STRATEGY
     * @see FormViewNormalizer::CHILDREN_AS_ARRAY
     * @see FormViewNormalizer::useNameAsChild
     */
    protected function useArrayForChildren($form)
    {
        $strategy = key_exists(
            self::NORMALIZATION_STRATEGY,
            $form->vars
        ) ? $form->vars[self::NORMALIZATION_STRATEGY] : null;

        return $strategy === self::CHILDREN_AS_ARRAY || $this->useNameAsChild($form);
    }

    /**
     * In some cases the children of the form should be serialized as an array of names where the value is true. This is
     * controlled by a var on the FormView.
     *
     * @param $form
     *
     * @return bool
     *
     * @see FormViewNormalizer::NORMALIZATION_STRATEGY
     * @see FormViewNormalizer::CHILDREN_AS_TRUE_NAMES
     */
    protected function useNameAsChild($form)
    {
        $strategy = key_exists(
            self::NORMALIZATION_STRATEGY,
            $form->vars
        ) ? $form->vars[self::NORMALIZATION_STRATEGY] : null;

        return $strategy === self::CHILDREN_AS_TRUE_NAMES;
    }
}
