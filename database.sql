create table `product` (
  `id` bigint(20) not null auto_increment,
  primary key(`id`),
  `name` varchar(64) not null,
  `invoice` varchar(64) not null,
  `serial` varchar(64) not null
) character set utf8mb4,
  collate utf8mb4_unicode_ci;

create table `product_info` (
  `id` bigint(20) not null auto_increment,
  primary key(`id`),
  `product` bigint(20) not null,
  key `key_product`(`product`),
  constraint `pi_f_product`
    foreign key(`product`) references `product`(`id`),
  `field` varchar(64) not null,
  unique `uniq_product_field`(`product`, `field`),
  `data` varchar(64) not null default ''
) character set utf8mb4,
  collate utf8mb4_unicode_ci;

create table `product_tag` (
  `id` bigint(20) not null auto_increment,
  primary key(`id`),
  `product` bigint(20) not null,
  constraint `pt_f_product`
    foreign key(`product`) references `product`(`id`),
  `tag` varchar(64) not null,
  unique `uniq_product_tag`(`product`,`tag`)
) character set utf8mb4,
  collate utf8mb4_unicode_ci;

create table `user` (
  `id` bigint(20) not null auto_increment,
  primary key(`id`),
  `name` varchar(64) not null,
  `notes` varchar(64) not null default ''
) character set utf8mb4,
  collate utf8mb4_unicode_ci;

create table `loan` (
  `id` bigint(20) not null auto_increment,
  primary key(`id`),
  `user` bigint(20) not null,
  constraint `l_f_user`
    foreign key(`user`) references `user`(`id`),
  `product` bigint(20) not null,
  constraint `l_f_product`
    foreign key(`product`) references `product`(`id`),
  `starttime` bigint(20) not null,
  `endtime` bigint(20) not null,
  `active` tinyint(1) not null default 1
) character set utf8mb4,
  collate utf8mb4_unicode_ci;

create table `kvs` (
  `key` varchar(64) not null,
  primary key(`key`),
  `value` varchar(64) not null default ''
) character set utf8mb4,
  collate utf8mb4_unicode_ci;
