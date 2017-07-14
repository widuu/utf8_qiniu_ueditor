# 已经停止更新这个版本，最新请查看[https://github.com/widuu/qiniu_ueditor_1.4.3](https://github.com/widuu/qiniu_ueditor_1.4.3)

# Ueditor结合七牛云存储上传图片、附件和图片在线管理的实现

#### 1.0版本修复bug

 - 提供多文件上传，解决了以前不能多文件上传问题，最大上传图片32张，最大上传附件10，如果感觉时间不足可修改getToken.php中的时间，现在是3600s
 
 - 修复了文件同名上传失败的问题，解决方案是同名上传覆盖，即bucket:key的方式
 
 - 修复了session丢失的问题
 
 - 修改了上一版执行安全漏洞
 
 - 还有个小bug就是上传覆盖之后，图片不会立即改变这是因为存储端还没有更新缓存，访问domain:key?var=1其实已经更新了
 
#### 功能截图


##### 多图片上传


![上传图片](http://yun.widuu.com/images/imagemore.png)

##### 同名文件覆盖

![图片在线管理](http://widuu.u.qiniudn.com/images/diff.png)

##### 多附件上传

![附件上传](http://widuu.u.qiniudn.com/images/filemore.png)

#### 后续

>后续会实现文件效验，实现大附件瞬间上传的机制


##### (1)安装使用

>[1]下载安装包-并解压到自己的目录

>[2]修改配置文件
 

  - 修改Ueditor根目录下的ueditor.config.js其中的配置如下



		,imagePath:"七牛分配的域名或者你绑定的域名"
		,savePath: ['your bucket']
	
		,filePath:"七牛分配的域名或者你绑定的域名"   
		,imageManagerPath:"七牛分配的域名或者你绑定的域名"



  - 修改根目录下/php/conf.php中的代码


	

	  	$QINIU_ACCESS_KEY	= 'your ak';
		$QINIU_SECRET_KEY	= 'your sk';
	
		$BUCKET = "your bucket";




>[3]OK了，下边就是你添加ueditor在你的网站上了，跟官方配置是一样的

### 效果图

##### 上传图片

![上传图片](http://widuu.u.qiniudn.com/images/fileupload.png)

##### 图片在线管理

![图片在线管理](http://widuu.u.qiniudn.com/images/imagemanner.png)

##### 附件上传

![附件上传](http://widuu.u.qiniudn.com/images/fileupload.png)

本程序由微度网络-网络技术中心提供技术支持和更新和维护，如果您有什么疑问可以去[http://www.widuu.com](http://www.widuu.com)来提交您的问题
