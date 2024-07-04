<?php

namespace Folklore\Support\Concerns;

trait SerializesResourceModel
{
    public function __serialize()
    {
        return [
            'model_key' => $this->model->getKey(),
            'model_class' => get_class($this->model),
            'model_data' => isset($this->data),
        ];
    }

    public function __unserialize(array $values)
    {
        $class = $values['model_class'];
        $this->model = $class::find($values['model_key']);
        if (isset($values['model_data']) && $values['model_data']) {
            $this->data = $this->model->data;
        }
    }
}
