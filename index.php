<?php
/**
 * Created by PhpStorm.
 * User: Dmitry Poliuha
 * Date: 22.04.2018
 * Time: 22:58
 */

require_once "SQL.php";

$dbname  = "test";
$dsn     = "mysql:host=localhost;dbname=$dbname;charset=utf8";
$user    = "root";
$pass    = "";
$options =
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];

$pdo = new PDO($dsn, $user, $pass, $options);


/* SELECT */
$query = new SQL($pdo);
$result = $query
    ->select()
    ->from("some_table")
    ->where("id", "<", 2)
    ->or("id", ">", 10)
    ->orderby("id")
    ->execute();
print_r($result);

/* INSERT */
$query = new SQL($pdo);
$params =
    [
        "name" => "weygewefwfwefhjwaeifw",
        "email" => "ewygfewofh@wrgferf.ewf"
    ];
$query
    ->insert("name", "email")
    ->into("some_table")
    ->values(":name", ":email")
    ->execute($params);

/* UPDATE */
$query = new SQL($pdo);
$params =
    [
        "email" => "1234@qw.erty"
    ];
$query
    ->update("some_table")
    ->set("email", ":email")
    ->where("id", "=", "2")
    ->execute($params);

/* DELETE */
$query = new SQL($pdo);
$query->delete("some_table")
    ->where("id", ">", 12)
    ->execute();

/* INNER JOIN */
$dsn     = "mysql:host=localhost;dbname=shop;charset=utf8";
$pdo = new PDO($dsn, $user, $pass, $options);
$query = new SQL($pdo);
$result = $query
    ->select("product.id", "product.name", "category.name", "product_type.name", "brands.name", "brands.country", "product.price")
    ->from("product")
    ->innerJoin("category")
    ->on("product.category_id", "=", "category.id")
    ->innerJoin("product_type")
    ->on("product.type_id", "=", "product_type.id")
    ->innerJoin("brands")
    ->on("product.brand_id", "=", "brands.id")
    ->orderby("price")
    ->limit("5")
    ->execute();
print_r($result);