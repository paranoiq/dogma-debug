<?php
/**
 * This file is part of the Dogma library (https://github.com/paranoiq/dogma)
 *
 * Copyright (c) 2012 Vlasta Neubauer (@paranoiq)
 *
 * For the full copyright and license information read the file 'license.md', distributed with this source code
 */

// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
// phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint

namespace Dogma\Debug;

use mysqli;
use mysqli_result;
use mysqli_stmt;
use mysqli_warning;
use function array_unshift;
use function call_user_func_array;

class MysqliStatementProxy extends mysqli_stmt
{

    /** @var mysqli_stmt */
    private $statement;

    public function __construct(mysqli $mysqli, string $query, mysqli_stmt $statement)
    {
        parent::__construct($mysqli, $query);

        $this->statement = $statement;
    }

    public function getOriginalStatement(): mysqli_stmt
    {
        return $this->statement;
    }

    public function __get($name)
    {
        return $this->statement->$name;
    }

    public function __call(string $method, array $args)
    {
        return call_user_func_array([$this->statement, $method], $args);
    }

    public function attr_set(int $attribute, int $value): bool
    {
        return $this->statement->attr_set($attribute, $value);
    }

    public function bind_param(string $types, mixed &...$vars): bool
    {
        array_unshift($vars, $types);

        return call_user_func_array([$this->statement, 'bind_param'], $vars);
    }

    public function bind_result(mixed &...$vars): bool
    {
        return call_user_func_array([$this->statement, 'bind_result'], $vars);
    }

    public function close()
    {
        $result = false;
        try {
            $result = $this->statement->close();
        } finally {
            Intercept::log(MysqliProxy::NAME, MysqliInterceptor::$intercept, 'mysqli_stmt::close', [], $result);
        }

        return $result;
    }

    public function data_seek(int $offset): void
    {
        $this->statement->data_seek($offset);
    }

    public function execute(?array $params = null): bool
    {
        $result = false;
        try {
            $result = $this->statement->execute($params);
        } finally {
            Intercept::log(MysqliProxy::NAME, MysqliInterceptor::$intercept, 'mysqli_stmt::execute', [], $result);
        }

        return $result;
    }

    public function fetch(): ?bool
    {
        return $this->statement->fetch();
    }

    public function get_warnings(): mysqli_warning|false
    {
        return $this->statement->get_warnings();
    }

    public function result_metadata(): mysqli_result|false
    {
        return $this->statement->result_metadata();
    }

    public function more_results(): bool
    {
        return $this->statement->more_results();
    }

    public function next_result(): bool
    {
        return $this->statement->next_result();
    }

    public function num_rows(): string|int
    {
        return $this->statement->num_rows();
    }

    public function send_long_data(int $param_num, string $data): bool
    {
        return $this->statement->send_long_data($param_num, $data);
    }

    public function free_result(): void
    {
        $this->statement->free_result();
    }

    public function reset(): bool
    {
        return $this->statement->reset();
    }

    public function prepare(string $query): bool
    {
        return $this->statement->prepare($query);
    }

    public function store_result(): bool
    {
        return $this->statement->store_result();
    }

    public function get_result(): mysqli_result|false
    {
        return $this->statement->get_result();
    }

}
