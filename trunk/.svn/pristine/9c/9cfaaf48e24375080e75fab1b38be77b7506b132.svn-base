alter table kkm_search ADD `geo` geometry NOT NULL;
update kkm_search set geo = point(lat, lng);

 ALTER TABLE kkm_search  ENGINE='MyISAM';
 
  ALTER TABLE kkm_search ADD SPATIAL INDEX(geo);