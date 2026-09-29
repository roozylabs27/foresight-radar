import { useState } from "react";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, router } from "@inertiajs/react";
import { Alert, Button, Form, Input, Checkbox } from "antd";
import { LockOutlined, UserOutlined } from "@ant-design/icons";

export default function Login({ errors: serverErrors }) {
    const [loading, setLoading] = useState(false);

    const onFinish = (values) => {
        setLoading(true);

        router.post(route("login"), values, {
            onError: () => {
                setLoading(false);
            },
            onFinish: () => {
                setLoading(false);
            },
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <div
                style={{ maxWidth: "400px", margin: "auto", padding: "50px 0" }}
            >
                {serverErrors?.email && (
                    <Alert
                        message={serverErrors.email}
                        type="error"
                        showIcon
                        style={{ marginBottom: "20px" }}
                    />
                )}
                <Form
                    name="login"
                    disabled={loading}
                    initialValues={{ remember: false }}
                    onFinish={onFinish}
                >
                    <Form.Item
                        name="email"
                        rules={[
                            {
                                required: true,
                                message: "Please input your Email!",
                            },
                        ]}
                    >
                        <Input prefix={<UserOutlined />} autoFocus placeholder="Email" />
                    </Form.Item>
                    <Form.Item
                        name="password"
                        rules={[
                            {
                                required: true,
                                message: "Please input your Password!",
                            },
                        ]}
                    >
                        <Input
                            prefix={<LockOutlined />}
                            type="password"
                            placeholder="Password"
                        />
                    </Form.Item>
                    <Form.Item>
                        <Form.Item
                            name="remember"
                            valuePropName="checked"
                            noStyle
                        >
                            <Checkbox>Remember me</Checkbox>
                        </Form.Item>
                    </Form.Item>
                    <Form.Item>
                        <Button
                            type="primary"
                            htmlType="submit"
                            className="login-form-button"
                            loading={loading}
                        >
                            Login
                        </Button>
                    </Form.Item>
                </Form>
            </div>
        </GuestLayout>
    );
}
