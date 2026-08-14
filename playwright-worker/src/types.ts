export interface Credentials {
  username: string;
  password: string;
}

export interface RoleAccount extends Credentials {
  role: string;
}

export interface TestForm {
  page: string;
  form_selector?: string;
  required_field_selector?: string;
  invalid_value?: string;
  valid_values?: Record<string, string>;
  submit_selector?: string;
}

export interface TestContext {
  protected_routes?: string[];
  admin_routes?: string[];
  login_url?: string;
  valid_credentials?: Credentials;
  invalid_credentials?: Credentials;
  role_accounts?: RoleAccount[];
  critical_pages?: string[];
  critical_workflows?: { name: string; steps: string[] }[];
  required_navigation?: string[];
  expected_routes?: { from: string; action_selector: string; expected_path: string }[];
  test_forms?: TestForm[];
  allow_duplicate_submission_test?: boolean;
  viewports?: { name: string; width: number; height: number }[];
  username_field_selector?: string;
  password_field_selector?: string;
  login_submit_selector?: string;
}

export interface RuleTask {
  rule_code: string;
  handler: string;
}

export interface WorkerInput {
  test_run_id: number;
  project_id: number;
  website_url: string;
  browsers: string[];
  timeout_ms: number;
  screenshot_dir: string;
  test_context: TestContext;
  rules: RuleTask[];
}

export type ResultStatus = 'PASS' | 'WARNING' | 'FAIL' | 'NOT_TESTABLE';

export interface Evidence {
  url?: string;
  final_url?: string;
  http_status?: number | null;
  redirected?: boolean;
  selector?: string | null;
  screenshot?: string | null;
  console_errors?: string[];
  network_errors?: string[];
  browser?: string;
  viewport?: string;
  timestamp: string;
  [extra: string]: unknown;
}

export interface RuleResult {
  rule_code: string;
  status: ResultStatus;
  tested_page: string | null;
  expected: string | null;
  observed: string | null;
  evidence: Evidence;
  duration_ms: number;
  applicability_override?: 'not_applicable';
}

export interface CheckContext {
  websiteUrl: string;
  testContext: TestContext;
  timeoutMs: number;
  screenshotDir: string;
  browserName: string;
}
