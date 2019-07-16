create table `product` (
  `id` bigint(20) not null auto_increment,
  primary key(`id`),
  `brand` varchar(64) not null,
  `name` varchar(64) not null,
  `invoice` varchar(64) not null,
  `serial` varchar(64) not null,
  unique `uniq_serial`(`serial`),
  `createtime` bigint(20) not null,
  `discardtime` bigint(20) default null
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

create table `template` (
  `id` bigint(20) not null auto_increment,
  primary key(`id`),
  `name` varchar(64) not null,
  unique key `key_name`(`name`)
) character set utf8mb4,
  collate utf8mb4_unicode_ci;

create table `template_info` (
  `id` bigint(20) not null auto_increment,
  primary key(`id`),
  `template` bigint(20) not null,
  key `key_template`(`template`),
  constraint `tf_f_template`
    foreign key(`template`) references `template`(`id`),
  `field` varchar(64) not null,
  unique `uniq_template_field`(`template`, `field`)
) character set utf8mb4,
  collate utf8mb4_unicode_ci;

create table `template_tag` (
  `id` bigint(20) not null auto_increment,
  primary key(`id`),
  `template` bigint(20) not null,
  constraint `tt_f_template`
    foreign key(`template`) references `template`(`id`),
  `tag` varchar(64) not null,
  unique `uniq_template_tag`(`template`, `tag`)
) character set utf8mb4,
  collate utf8mb4_unicode_ci;

create table `user` (
  `id` bigint(20) not null auto_increment,
  primary key(`id`),
  `name` varchar(64) not null,
  unique `uniq_name`(`name`),
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
  `returntime` bigint(20) default null
) character set utf8mb4,
  collate utf8mb4_unicode_ci;

create table `service` (
  `id` bigint(20) not null auto_increment,
  primary key(`id`),
  `product` bigint(20) not null,
  constraint `s_f_product`
    foreign key(`product`) references `product`(`id`),
  `starttime` bigint(20) not null,
  `returntime` bigint(20) default null
) character set utf8mb4,
  collate utf8mb4_unicode_ci;

create table `inventory` (
  `id` bigint(20) not null auto_increment,
  primary key(`id`),
  `starttime` bigint(20) not null,
  `endtime` bigint(20) default null
) character set utf8mb4,
  collate utf8mb4_unicode_ci;

create table `inventory_product` (
  `id` bigint(20) not null auto_increment,
  primary key(`id`),
  `inventory` bigint(20) not null,
  constraint `i_f_inventory`
    foreign key(`inventory`) references `inventory`(`id`),
  `product` bigint(20) not null,
  constraint `i_f_product`
    foreign key(`product`) references `product`(`id`),
  unique `uniq_inventory_product`(`inventory`, `product`)
) character set utf8mb4,
  collate utf8mb4_unicode_ci;

create table `kvs` (
  `key` varchar(64) not null,
  primary key(`key`),
  `value` varchar(64) not null default ''
) character set utf8mb4,
  collate utf8mb4_unicode_ci;
