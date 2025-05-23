<?php

namespace App\Repository;
use Illuminate\Database\Eloquent\Model;

abstract class IRepository implements RepositoryInterface
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $model;

    protected $optimisticLock;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        return $this->model->all();
    }

    public function find(int $id)
    {
        return $this->model->findOrFail($id);
    }

    public function findBy(string $column, $value, array $select = ['*'])
    {
        return $this->model
            ->select($select)
            ->where($column, $value)
            ->firstOrFail();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, int $id)
    {
        $model = $this->model->find($id);
        $model->update($data);
        return $model;
    }

    public function delete(int $id)
    {
        return $this->model->destroy($id);
    }

    /**
     * @param array $criteria
     * @param array $joins
     * @param array $attributes
     * @param bool $is_sort
     * @param string $sort_field
     * @param string $sort
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function where(array $criteria = [], array $joins = [], array $attributes = ['*'], bool $is_sort = false, string $sort_field = 'created_at', string $sort = 'ASC')
    {
        $query = $this->model->query();

        // Example criteria ['age', '>=', 18, 'and/or']
        if (!empty($criteria)) {
            foreach ($criteria as $criterion) {
                $column = $criterion[0];
                $operator = $criterion[1];
                $value = $criterion[2];
                $boolean = isset($criterion[3]) ? $criterion[3] : 'and';

                // Check if the operator is 'in' and use whereIn
                if (strtolower($operator) === 'between') {
                    $query->whereBetween($column, [$value[0], $value[1]], $boolean);
                }
                else if (strtolower($operator) === 'in') {
                    $query->whereIn($column, $value, $boolean);
                } elseif (strtolower($operator) === 'not in') {
                    $query->whereNotIn($column, $value, $boolean);
                }else {
                    $query->where($column, $operator, $value, $boolean);
                }
            }
        }

        if (!empty($joins)) {
            foreach ($joins as $join) {
                $table = $join[0];
                $firstColumn = $join[1];
                $operator = $join[2];
                $secondColumn = $join[3];
                $joinType = isset($join[4]) ? $join[4] : 'inner';

                $query->join($table, $firstColumn, $operator, $secondColumn, $joinType);
            }
        }

        $query->select($attributes);

        if ($is_sort) {
            $query->orderBy($sort_field, $sort);
        }

        return $query;
    }

    public function increment(string $field, int $amount = 1, array $conditions = [])
    {
        return $this->model->where($conditions)->increment($field, $amount);
    }

    public function decrement(string $field, int $amount = 1, array $conditions = [])
    {
        return $this->model->where($conditions)->decrement($field, $amount);
    }

    public function updateWithOptimisticLocking(array $data, int $id, int $version)
    {
        $primaryKey = $this->model->getKeyName();

        $model = $this->model->where($primaryKey, $id)->where('version', $version)->first();

        if (!$model) {
            throw new \Exception('Model not found or version mismatch.');
        }

        // Increment the version value
        $model->version++;

        // Update other data fields
        $model->fill($data);

        // Save the updated model
        $model->save();

        return $model;
    }

    public function createAndReturnId(array $data)
    {
        return $this->model->create($data)->id;
    }
}
