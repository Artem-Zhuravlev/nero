<?php
// Minimal OpenCart runtime for driving a catalog event handler in the PHP test runner (SPEC-080 TEST-SD-006). Global namespace on purpose.

namespace {
    if (!class_exists('Registry')) {
        class Registry
        {
            private $data = [];

            public function get($key)
            {
                return $this->data[$key] ?? null;
            }

            public function set($key, $value): void
            {
                $this->data[$key] = $value;
            }
        }

        abstract class Controller
        {
            protected $registry;

            public function __construct($registry)
            {
                $this->registry = $registry;
            }

            public function __get($key)
            {
                return $this->registry->get($key);
            }
        }
    }

    final class StubBag
    {
        public $values;

        public function __construct(array $values = [])
        {
            $this->values = $values;
        }

        public function get($key)
        {
            return $this->values[$key] ?? null;
        }
    }

    final class StubLoad
    {
        public $registry;
        public $models = [];

        public function model($name): void
        {
            $this->registry->set('model_' . str_replace('/', '_', $name), $this->models[$name] ?? new StubModel([]));
        }
    }

    /** Answers any method with the value configured for its name (a callable is called). */
    final class StubModel
    {
        private $answers;

        public function __construct(array $answers)
        {
            $this->answers = $answers;
        }

        public function __call($name, $args)
        {
            $v = $this->answers[$name] ?? null;

            return $v instanceof \Closure ? $v(...$args) : $v;
        }
    }

    final class StubUrl
    {
        public function link($route, $args = '', $secure = false)
        {
            if ($route === 'common/home') {
                return 'http://shop.test/';
            }

            return 'http://shop.test/index.php?route=' . $route . ($args !== '' ? '&amp;' . $args : '');
        }
    }

    final class StubDb
    {
        public $row = [];

        public function query($sql)
        {
            return (object)['row' => $this->row, 'rows' => $this->row ? [$this->row] : []];
        }
    }

    final class StubLog
    {
        public $lines = [];

        public function write($line): void
        {
            $this->lines[] = $line;
        }
    }
}
