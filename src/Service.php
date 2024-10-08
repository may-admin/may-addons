<?php
declare(strict_types=1);

namespace mayadmin\addons;

use think\Route;
use think\Console;
use think\helper\Str;
use think\facade\Config;
use think\facade\Lang;
use think\facade\Cache;
use think\facade\Log;

class Service extends \think\Service
{
    // addons 路径
    protected $addons_path;
    //存放[插件名称]列表数据
    protected array $addons_data = [];
    //存放[插件ini所有信息]列表数据
    protected array $addons_data_list = [];
    //模块所有[config.php]里的信息存放
    protected array $addons_data_list_config = [];
    
    public function register()
    {
        Log::info('addons-Service-register');
        
        $this->app->bind('addons', Service::class);
        // 无则创建addons目录
        $this->addons_path = $this->getAddonsPath();
        // 加载系统语言包
        $this->loadLang();
        // 自动载入插件
        $this->autoload();
    }
    
    public function boot()
    {
        Log::info('addons-Service-boot');
        
        $commands = [
            'addons:app'        => command\App::class,
            'addons:controller' => command\Controller::class,
            'addons:model'      => command\Model::class,
            'addons:view'       => command\View::class,
            'addons:validate'   => command\Validate::class,
            'addons:config'     => command\Config::class,
            'addons:lang'       => command\Lang::class,
            'addons:plugin'     => command\Plugin::class,
        ];
        Console::starting(function (Console $console) use ($commands) {
            foreach($commands as $key => $command){
                $console->addCommand($command, is_numeric($key) ? '' : $key);
            }
        });
        
        //注册HttpRun事件监听,触发后注册全局中间件到开始位置
        $this->registerRoutes(function (Route $route) {
            // 路由脚本
            $execute = '\\mayadmin\\addons\\Route::execute';
            // 注册控制器路由
            $route->rule('addons/:addon/[:controller]/[:action]', $execute);
        });
    }
    
    /**
     * @Description: todo(初始化插件目录)
     * @author 苏晓信 <654108442@qq.com>
     * @date 2024年08月23日
     * @throws
     */
    public function getAddonsPath()
    {
        $addons_path = $this->app->getRootPath().'addons'.DIRECTORY_SEPARATOR;
        if (!is_dir($addons_path)) {
            @mkdir($addons_path, 0755, true);
        }
        return $addons_path;
    }
    
    /**
     * @Description: todo(自动载入插件语言包)
     * @author 苏晓信 <654108442@qq.com>
     * @date 2024年08月23日
     * @throws
     */
    private function loadLang()
    {
        Lang::load([
            $this->app->getRootPath() . '/vendor/may-admin/addons/src/lang/zh-cn.php',
        ]);
    }
    
    /**
     * @Description: todo(自动载入钩子插件)
     * @author 苏晓信 <654108442@qq.com>
     * @date 2024年08月23日
     * @throws
     */
    private function autoload()
    {
        // 是否处理自动载入
        if (Config::get('addons.autoload', true)) {
            $config = Config::get('addons');
            // 读取插件目录及钩子列表
            $base = get_class_methods('\\mayadmin\\addons\\Addons');
            $base = array_merge($base, ['init', 'initialize', 'install', 'uninstall', 'enabled', 'disabled']);
            // 读取插件目录中的php文件
            foreach (glob($this->getAddonsPath() . '*/*.php') as $addons_file) {
                // 格式化路径信息
                $info = pathinfo($addons_file);
                // 获取插件目录名
                $name = pathinfo($info['dirname'], PATHINFO_FILENAME);
                // 找到插件入口文件
                if (Str::lower($info['filename']) === 'plugin') {
                    // 读取出所有公共方法[addons\插件名\Plugin.php]文件
                    if (!class_exists('\\addons\\' . $name . '\\' . $info['filename'])) {
                        continue;
                    }
                    $methods = get_class_methods('\\addons\\' . $name . '\\' . $info['filename']);
                    // 读取出信息[addons\插件名\plugin.ini]
                    $ini = $info['dirname'] . DIRECTORY_SEPARATOR . 'plugin.ini';
                    if (!is_file($ini)) {
                        continue;
                    }
                    $addon_config = parse_ini_file($ini, true, INI_SCANNER_TYPED) ? : [];
                    if(isset($addon_config['name']) && !empty($addon_config['name'])){
                        $this->addons_data[]                                  = $addon_config['name'];
                        $this->addons_data_list[$addon_config['name']]        = $addon_config;
                        if (file_exists($this->getAddonsPath() . $name . '/config.php')) {
                            $this->addons_data_list_config[$addon_config['name']] = include $this->getAddonsPath() . $name . '/config.php';
                        }
                    }
                    // // 跟插件基类方法做比对，得到差异结果
                    // setAddonConfig($config, $methods, $base, $name);
                }
            }
            //插件配置信息保存到缓存
            // Cache::set('addons_config', $config);
            //插件列表
            Cache::set('addons_data', $this->addons_data);
            //插件ini列表
            Cache::set('addons_data_list', $this->addons_data_list);
            //插件config列表
            Cache::set('addons_data_list_config', $this->addons_data_list_config);
            // Config::set($config, 'addons');
        }
    }
}