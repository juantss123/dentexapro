<?php session_start(); if(!isset($_SESSION['admin_id'])){header('Location:../login.php');exit;}
require_once '../config.php';
$id=intval($_GET['id']??0); if($id){$stmt=$mysqli->prepare("DELETE FROM patients WHERE id=?");$stmt->bind_param("i",$id);$stmt->execute();}
header('Location:index.php');exit;
