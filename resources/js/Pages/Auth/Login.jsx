import { useEffect, useState } from "react";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, Link, useForm } from "@inertiajs/react";
import { Alert, Button, Form, Input, Checkbox, message } from "antd";
import { LockOutlined, UserOutlined } from "@ant-design/icons";
import axios from "axios";

export default function Login() {
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState({});

    const onFinish = async (values) => {
        setLoading(true);

        try {
            const response = await axios.post(route("login"), values);

            if(response.status == 200) {
                setErrors({})
                window.location = response.data.data.url
            }
        } catch (error) {
            setLoading(false);
            switch (error.response.status) {
                case 422:
                    setErrors(error.response.data.errors)
                    break;
                default:
                    message.error(error.message);
            }
        }
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <div
                style={{ maxWidth: "400px", margin: "auto", padding: "50px 0" }}
            >
                {errors.email && (
                    <Alert message={errors.email} type="error" showIcon style={{ marginBottom: "20px" }} />
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
                    <Form.Item >
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
