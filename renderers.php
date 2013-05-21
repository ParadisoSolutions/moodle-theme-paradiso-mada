<?php
include_once($CFG->dirroot . "/course/renderer.php");
class theme_mada_core_renderer extends core_renderer {

    /**
     * Renders a custom menu object (located in outputcomponents.php)
     *
     * The custom menu this method override the render_custom_menu function
     * in outputrenderers.php
     * @staticvar int $menucount
     * @param custom_menu $menu
     * @return string
     */
    protected function render_custom_menu(custom_menu $menu) {

        // If the menu has no children return an empty string
        if (!$menu->has_children()) {
            return '';
        }

        // Add a login or logout link
        if (isloggedin()) {
            $branchlabel = get_string('logout');
            $branchurl   = new moodle_url('/login/logout.php');
        } else {
            $branchlabel = get_string('login');
            $branchurl   = new moodle_url('/login/index.php');
        }
        $branch = $menu->add($branchlabel, $branchurl, $branchlabel, -1);

        // Initialise this custom menu
        $content = html_writer::start_tag('ul', array('class'=>'dropdown dropdown-horizontal'));
        // Render each child
        foreach ($menu->get_children() as $item) {
            $content .= $this->render_custom_menu_item($item);
        }
        // Close the open tags
        $content .= html_writer::end_tag('ul');
        // Return the custom menu
        return $content;
    }

    /**
     * Renders a custom menu node as part of a submenu
     *
     * The custom menu this method override the render_custom_menu_item function
     * in outputrenderers.php
     *
     * @see render_custom_menu()
     *
     * @staticvar int $submenucount
     * @param custom_menu_item $menunode
     * @return string
     */
    protected function render_custom_menu_item(custom_menu_item $menunode) {
        // Required to ensure we get unique trackable id's
        static $submenucount = 0;
        $content = html_writer::start_tag('li');
        if ($menunode->has_children()) {
            // If the child has menus render it as a sub menu
            $submenucount++;
            if ($menunode->get_url() !== null) {
                $url = $menunode->get_url();
            } else {
                $url = '#cm_submenu_'.$submenucount;
            }
            $content .= html_writer::start_tag('span', array('class'=>'customitem'));
            $content .= html_writer::link($url, $menunode->get_text(), array('title'=>$menunode->get_title()));
            $content .= html_writer::end_tag('span');
            $content .= html_writer::start_tag('ul');
            foreach ($menunode->get_children() as $menunode) {
                $content .= $this->render_custom_menu_item($menunode);
            }
            $content .= html_writer::end_tag('ul');
        } else {
            // The node doesn't have children so produce a final menuitem

            if ($menunode->get_url() !== null) {
                $url = $menunode->get_url();
            } else {
                $url = '#';
            }
            $content .= html_writer::link($url, $menunode->get_text(), array('title'=>$menunode->get_title()));
        }
        $content .= html_writer::end_tag('li');
        // Return the sub menu
        return $content;
    }

    /**
     * Copied from core_renderer with one minor change - changed $this->output->render() call to $this->render()
     *
     * @param navigation_node $item
     * @return string
     */
    protected function render_navigation_node(navigation_node $item) {
        $content = $item->get_content();
        $title = $item->get_title();
        if ($item->icon instanceof renderable && !$item->hideicon) {
            $icon = $this->render($item->icon);
            $content = $icon.$content; // use CSS for spacing of icons
        }
        if ($item->helpbutton !== null) {
            $content = trim($item->helpbutton).html_writer::tag('span', $content, array('class'=>'clearhelpbutton'));
        }
        if ($content === '') {
            return '';
        }
        if ($item->action instanceof action_link) {
            //adds class dimmed to hidden courses and categories
            $link = $item->action;
            if ($item->hidden) {
                $link->add_class('dimmed');
            }
            $content = $this->render($link);
        } else if ($item->action instanceof moodle_url) {
            $attributes = array();
            if ($title !== '') {
                $attributes['title'] = $title;
            }
            if ($item->hidden) {
                $attributes['class'] = 'dimmed_text';
            }
            $content = html_writer::link($item->action, $content, $attributes);

        } else if (is_string($item->action) || empty($item->action)) {
            $attributes = array();
            if ($title !== '') {
                $attributes['title'] = $title;
            }
            if ($item->hidden) {
                $attributes['class'] = 'dimmed_text';
            }
            $content = html_writer::tag('span', $content, $attributes);
        }
        return $content;
    }

}
class theme_mada_core_course_renderer extends core_course_renderer {

    /**
     * A cache of strings
     * @var stdClass
     */
    protected $strings;

    /**
     * Override the constructor so that we can initialise the string cache
     *
     * @param moodle_page $page
     * @param string $target
     */
    public function __construct(moodle_page $page, $target) {
        $this->strings = new stdClass;
        parent::__construct($page, $target);
    }

    /**
     * Renderers a structured array of courses and categories into a nice
     * XHTML tree structure.
     *
     * This method was designed initially to display the front page course/category
     * combo view. The structure can be retrieved by get_course_category_tree()
     *
     * @param array $structure
     * @return string
     */
    public function course_category_tree(array $structure) {
        $this->strings->summary = get_string('summary');

        // Generate an id and the required JS call to make this a nice widget
        $id = html_writer::random_id('course_category_tree');
        $this->page->requires->js_init_call('M.util.init_toggle_class_on_click', array($id, '.category.with_children .category_label', 'collapsed', '.category.with_children'));

        // Start content generation
        $content = html_writer::start_tag('div', array('class'=>'course_category_tree', 'id'=>$id));
        foreach ($structure as $category) {
			$content .= $this->course_category_tree_category($category);
			
        }
        $content .= html_writer::start_tag('div', array('class'=>'controls'));
        $content .= html_writer::tag('div', get_string('collapseall'), array('class'=>'addtoall expandall'));
        $content .= html_writer::tag('div', get_string('expandall'), array('class'=>'removefromall collapseall'));
        $content .= html_writer::end_tag('div');
        $content .= html_writer::end_tag('div');
		

        // Return the course category tree HTML
        return $content;
    }

    /**
     * Renderers a category for use with course_category_tree
     *
     * @param array $category
     * @param int $depth
     * @return string
     */
    protected function course_category_tree_category(stdClass $category, $depth=1) {
        $content = '';
        $hassubcategories = (isset($category->categories) && count($category->categories)>0);
        $hascourses = (isset($category->courses) && count($category->courses)>0);
        $classes = array('category');
        if ($category->parent != 0) {
            $classes[] = 'subcategory';
        }
        if (empty($category->visible)) {
            $classes[] = 'dimmed_category';
        }
        if ($hassubcategories || $hascourses) {
            $classes[] = 'with_children';
            if ($depth > 1) {
                $classes[] = 'collapsed';
            }
        }
        $categoryname = format_string($category->name, true, array('context' => get_context_instance(CONTEXT_COURSECAT, $category->id)));

        $content .= html_writer::start_tag('div', array('class'=>join(' ', $classes)));
        $content .= html_writer::start_tag('div', array('class'=>'category_label'));
        $content .= html_writer::link(new moodle_url('/course/category.php', array('id'=>$category->id)), $categoryname, array('class'=>'category_link'));
        $content .= html_writer::end_tag('div');
        if ($hassubcategories) {
            $content .= html_writer::start_tag('div', array('class'=>'subcategories'));
            foreach ($category->categories as $subcategory) {
                $content .= $this->course_category_tree_category($subcategory, $depth+1);
            }
            $content .= html_writer::end_tag('div');
        }
        if ($hascourses) {
            $content .= html_writer::start_tag('div', array('class'=>'courses'));
            $coursecount = 0;
            foreach ($category->courses as $course) {
                
				$classes = array('course');	
                $linkclass = 'course_link';
                if (!$course->visible) {
                    $linkclass .= ' dimmed';
                }
                $coursecount ++;
                $classes[] = ($coursecount%2)?'odd':'even';
                $content .= html_writer::start_tag('div', array('class'=>join(' ', $classes)));
                $content .= html_writer::link(new moodle_url('/course/view.php', array('id'=>$course->id)), format_string($course->fullname), array('class'=>$linkclass));
                //$content .= html_writer::start_tag('div', array('class'=>'course_info clearfix'));

                // print enrol info
                if ($icons = enrol_get_course_info_icons($course)) {
                    foreach ($icons as $pix_icon) {
                        $content .= $this->render($pix_icon);
                    }
                }

                if ($course->summary) {
					//developed by Paradiso: Santiago Rivera//
                    //$image = html_writer::empty_tag('img', array('src'=>$this->output->pix_url('i/info'), 'alt'=>$this->strings->summary));
                    //$content .= html_writer::link(new moodle_url('/course/info.php', array('id'=>$course->id)), $image, array('title'=>$this->strings->summary));
					//developed by Paradiso//
					$content .= html_writer::start_tag('div', array('class'=>'summary_mada') );
					$content .= $course->summary;
					$content .= html_writer::end_tag('div');
					
                }
                //$content .= html_writer::end_tag('div');
                $content .= html_writer::end_tag('div');
            }
            $content .= html_writer::end_tag('div');
        }
        $content .= html_writer::end_tag('div');
        return $content;
    }
}